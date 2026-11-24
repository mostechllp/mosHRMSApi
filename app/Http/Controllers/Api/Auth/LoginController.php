<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Mail\LoginOtpMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class LoginController extends ApiController
{
    #[OA\Post(
        path: "/api/auth/login",
        operationId: "loginUser",
        summary: "Login user",
        description: "Login and return an access token",
        tags: ["Authentication"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["username", "password"],
            properties: [
                new OA\Property(property: "username", type: "string", example: "admin@example.com"),
                new OA\Property(property: "password", type: "string", example: "password123")
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Successful login",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "access_token", type: "string"),
                new OA\Property(property: "token_type", type: "string", example: "bearer"),
                new OA\Property(property: "user", type: "object")
            ]
        )
    )]
    #[OA\Response(response: 401, description: "Unauthorized")]
    #[OA\Response(response: 422, description: "Validation Error")]
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation Error', 422, $validator->errors());
        }

        $loginField = filter_var($request->username, FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'username';

        if (
            !$token = auth('api')->attempt([
                $loginField => $request->username,
                'password' => $request->password
            ])
        ) {
            return $this->error('Unauthorized - Invalid credentials', 401);
        }

        $user = auth('api')->user();

        if ($user->status !== 'active') {
            auth('api')->logout();
            return $this->error('Account is inactive. Please contact the administrator.', 403);
        } else if ($user->status == 'onboarding') {
            auth('api')->logout();
            return $this->error('Account is under onboarding and is not yet active.', 403);
        }
        $agreedNow = $request->boolean('agreeToTerms');

        if ($agreedNow) {
            $user->agree_to_terms = true;
            $user->save();
        }

        if (!$agreedNow && !$user->agree_to_terms) {
            auth('api')->logout();
            return $this->error('You must agree to the terms and conditions to login.', 403);
        }

        return $this->respondWithToken($token, $user);
    }

    /**
     * Send One-Time Password (OTP) to user's registered email for passwordless login.
     * POST /api/auth/login/send-otp
     */
    public function sendLoginOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required_without:username|string',
            'username' => 'nullable',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation Error', 422, $validator->errors());
        }

        $identifier = $request->input('email') ?? $request->input('username');

        $user = User::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (!$user) {
            return $this->error('No account found associated with this email or username.', 404);
        }

        if ($user->status !== 'active') {
            return $this->error('Account is inactive. Please contact your administrator.', 403);
        } elseif ($user->status === 'onboarding') {
            return $this->error('Account is under onboarding and is not yet active.', 403);
        }

        // Rate-limiting check: prevent requesting new OTP within 60 seconds
        if (Schema::hasTable('login_otps')) {
            $recent = DB::table('login_otps')
                ->where('email', $user->email)
                ->where('created_at', '>=', now()->subSeconds(60))
                ->first();

            if ($recent) {
                return $this->error('An OTP was recently sent. Please wait 60 seconds before requesting a new code.', 429);
            }
        }

        // Generate secure 6-digit numeric OTP
        $otp = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(10);

        // Store OTP in database or fallback to cache
        if (Schema::hasTable('login_otps')) {
            DB::table('login_otps')->updateOrInsert(
                ['email' => $user->email],
                [
                    'otp'        => $otp,
                    'expires_at' => $expiresAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        } else {
            cache()->put('login_otp_' . $user->email, [
                'otp'        => $otp,
                'expires_at' => $expiresAt->timestamp,
            ], $expiresAt);
        }

        // Send OTP via Email
        try {
            $displayName = trim(($user->employee?->first_name ?? '') . ' ' . ($user->employee?->last_name ?? '')) ?: $user->username;
            Mail::to($user->email)->send(new LoginOtpMail($otp, $displayName, 10));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send login OTP email to {$user->email}: " . $e->getMessage(), [
                'exception' => $e
            ]);
            return $this->error('Failed to send OTP email: ' . $e->getMessage(), 500);
        }

        return $this->success([
            'email'              => $this->maskEmail($user->email),
            'expires_in_seconds' => 600,
        ], 'A 6-digit verification code has been sent to your registered email.');
    }

    /**
     * Verify OTP and log in the user, returning a JWT access token.
     * POST /api/auth/login/verify-otp or POST /api/auth/login-otp
     */
    public function loginWithOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'        => 'required_without:username|string',
            'username'     => 'required_without:email|string',
            'otp'          => 'required|string|size:6',
            'agreeToTerms' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation Error', 422, $validator->errors());
        }

        $identifier = $request->input('email') ?? $request->input('username');

        $user = User::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (!$user) {
            return $this->error('Invalid credentials.', 401);
        }

        if ($user->status !== 'active') {
            return $this->error('Account is inactive. Please contact your administrator.', 403);
        } elseif ($user->status === 'onboarding') {
            return $this->error('Account is under onboarding and is not yet active.', 403);
        }

        // Verify OTP from database or cache
        $isValidOtp = false;

        if (Schema::hasTable('login_otps')) {
            $record = DB::table('login_otps')->where('email', $user->email)->first();
            if ($record && hash_equals((string) $record->otp, (string) $request->otp)) {
                if (Carbon::parse($record->expires_at)->isFuture()) {
                    $isValidOtp = true;
                }
            }
        } else {
            $cached = cache()->get('login_otp_' . $user->email);
            if ($cached && hash_equals((string) $cached['otp'], (string) $request->otp) && $cached['expires_at'] > time()) {
                $isValidOtp = true;
            }
        }

        if (!$isValidOtp) {
            return $this->error('Invalid or expired verification code. Please request a new OTP.', 401);
        }

        // Handle terms and conditions agreement
        $agreedNow = $request->boolean('agreeToTerms');
        if ($agreedNow) {
            $user->agree_to_terms = true;
            $user->save();
        }

        if (!$agreedNow && !$user->agree_to_terms) {
            return $this->error('You must agree to the terms and conditions to login.', 403);
        }

        // Invalidate used OTP immediately (one-time use)
        if (Schema::hasTable('login_otps')) {
            DB::table('login_otps')->where('email', $user->email)->delete();
        }
        cache()->forget('login_otp_' . $user->email);

        // Generate JWT Token
        if (!$token = auth('api')->login($user)) {
            return $this->error('Failed to generate authentication token.', 500);
        }

        return $this->respondWithToken($token, $user);
    }

    /**
     * Mask email address for response privacy (e.g., jo***@example.com).
     */
    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }

        $name = $parts[0];
        $domain = $parts[1];

        if (strlen($name) <= 2) {
            $maskedName = substr($name, 0, 1) . '*';
        } else {
            $maskedName = substr($name, 0, 2) . str_repeat('*', max(2, strlen($name) - 2));
        }

        return $maskedName . '@' . $domain;
    }

    /**
     * Response with token + user + employee + roles
     */
    protected function respondWithToken($token, $user): JsonResponse
    {
        $employee = $user->employee;
        $role = $user->role;

        return $this->success([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,

            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'status' => $user->status,
                'avatar' => $employee ? $employee->avatar_url : $user->avatar_url,
                'type' => $user->type,
                'role' => $role ? [
                    'id' => $role->id,
                    'name' => $role->name,
                ] : null,
                'employee' => $employee ? [
                    'id' => $employee->id,
                    'name' => trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
                    'employee_id' => $employee->employee_id,
                ] : null,
                'permissions' => $this->formatPermissions($user),
                'sidebar_modules' => $this->formatSidebarModules($user),
            ]
        ], 'Login successful');
    }

    #[OA\Post(
        path: "/api/auth/logout",
        operationId: "logoutUser",
        summary: "Logout user",
        description: "Logout the currently authenticated user",
        security: [["bearerAuth" => []]],
        tags: ["Authentication"]
    )]
    #[OA\Response(response: 200, description: "Successfully logged out")]
    #[OA\Response(response: 401, description: "Unauthenticated")]
    public function logout(): JsonResponse
    {
        auth('api')->logout();
        return $this->success(null, 'Successfully logged out');
    }

    #[OA\Post(
        path: "/api/auth/refresh",
        operationId: "refreshToken",
        summary: "Refresh auth token",
        description: "Refresh the current access token",
        security: [["bearerAuth" => []]],
        tags: ["Authentication"]
    )]
    #[OA\Response(
        response: 200,
        description: "Token refreshed successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "access_token", type: "string"),
                new OA\Property(property: "token_type", type: "string", example: "bearer"),
                new OA\Property(property: "user", type: "object")
            ]
        )
    )]
    #[OA\Response(response: 401, description: "Unauthenticated")]
    public function refresh(): JsonResponse
    {
        try {
            $token = auth('api')->refresh();
            $user = auth('api')->setToken($token)->user();

            if (!$user) {
                return $this->error('User not found', 404);
            }

            return $this->respondWithToken($token, $user);
        } catch (\Exception $e) {
            return $this->error('Token could not be refreshed. ' . $e->getMessage(), 401);
        }
    }

    #[OA\Get(
        path: "/api/auth/me",
        operationId: "getAuthenticatedUser",
        summary: "Get logged-in user",
        description: "Get details of the currently authenticated user",
        security: [["bearerAuth" => []]],
        tags: ["Authentication"]
    )]
    #[OA\Response(response: 200, description: "Successful operation")]
    #[OA\Response(response: 401, description: "Unauthenticated")]
    public function me(): JsonResponse
    {
        if (!auth('api')->check()) {
            return $this->error('Unauthenticated', 401);
        }

        $user = auth('api')->user();
        return $this->respondWithToken(null, $user);
    }

    public function getMyPermissions(): JsonResponse
    {
        $user = auth('api')->user();
        return $this->success($this->formatPermissions($user));
    }

    public function getMySidebarModules(): JsonResponse
    {
        $user = auth('api')->user();
        return $this->success($this->formatSidebarModules($user));
    }

    protected function formatPermissions($user)
    {
        if (!$user->role)
            return [];

        // if ($user->role->name === 'Admin') {
        //     return ['all' => true];
        // }

        return $user->role->permissions->mapWithKeys(function ($p) {
            return [
                $p->module->slug => [
                    'read' => (bool) $p->can_read,
                    'edit' => (bool) $p->can_edit,
                    'delete' => (bool) $p->can_delete,
                ]
            ];
        });
    }

    protected function formatSidebarModules($user)
    {
        if (!$user->role)
            return [];

        // if ($user->role->name === 'Admin') {
        //     return \App\Models\Module::where('status', 'active')->get();
        // }

        return $user->role->permissions()
            ->where('can_read', true)
            ->with([
                'module' => function ($q) {
                    $q->where('status', 'active');
                }
            ])
            ->get()
            ->pluck('module')
            ->filter();
    }
}