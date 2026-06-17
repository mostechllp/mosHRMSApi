<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Mail\VerificationCodeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ForgotPasswordApiController extends ApiController
{
    #[OA\Post(
        path: "/api/auth/forgot-password/send-code",
        operationId: "sendResetCode",
        summary: "Send password reset code",
        description: "Sends a 6-digit verification code to the user's registered email address.",
        tags: ["Authentication"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["email"],
            properties: [
                new OA\Property(
                    property: "email",
                    type: "string",
                    format: "email",
                    example: "admin@example.com"
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Reset code sent successfully"
    )]
    #[OA\Response(
        response: 422,
        description: "Validation Error"
    )]
    #[OA\Response(
        response: 500,
        description: "Failed to send email"
    )]
    public function sendResetCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => $code,
                'created_at' => Carbon::now()
            ]
        );

        try {
            Mail::to($request->email)->send(new VerificationCodeMail($code));

            return $this->success(
                null,
                'Reset code sent to your email.'
            );
        } catch (\Exception $e) {
            return $this->error(
                'Failed to send reset code: ' . $e->getMessage(),
                500
            );
        }
    }

    #[OA\Post(
        path: "/api/auth/forgot-password/reset",
        operationId: "resetPassword",
        summary: "Reset password",
        description: "Reset user password using email and verification code.",
        tags: ["Authentication"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: [
                "email",
                "code",
                "password",
                "password_confirmation"
            ],
            properties: [
                new OA\Property(
                    property: "email",
                    type: "string",
                    format: "email",
                    example: "admin@example.com"
                ),
                new OA\Property(
                    property: "code",
                    type: "string",
                    example: "123456"
                ),
                new OA\Property(
                    property: "password",
                    type: "string",
                    format: "password",
                    example: "NewPassword@123"
                ),
                new OA\Property(
                    property: "password_confirmation",
                    type: "string",
                    format: "password",
                    example: "NewPassword@123"
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Password reset successfully"
    )]
    #[OA\Response(
        response: 422,
        description: "Invalid or expired verification code"
    )]
    #[OA\Response(
        response: 404,
        description: "User not found"
    )]
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->code)
            ->first();

        if (
            !$record ||
            Carbon::parse($record->created_at)
                ->addMinutes(15)
                ->isPast()
        ) {
            return $this->error(
                'The verification code is invalid or has expired.',
                422
            );
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->error(
                'User not found.',
                404
            );
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        return $this->success(
            null,
            'Password has been updated successfully.'
        );
    }
}