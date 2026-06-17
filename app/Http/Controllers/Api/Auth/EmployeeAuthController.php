<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class EmployeeAuthController extends ApiController
{
    public function __construct()
    {
        $this->middleware('auth:employee_api', ['except' => ['login']]);
    }

    /**
     * Get a JWT via given credentials.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'company_email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation Error', 422, $validator->errors());
        }

        $credentials = [
            'company_email' => $request->company_email,
            'password' => $request->password,
        ];

        if (! $token = auth('employee_api')->attempt($credentials)) {
            return $this->error('Invalid credentials', 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Get the authenticated Employee.
     */
    public function me(): JsonResponse
    {
        return $this->success(auth('employee_api')->user());
    }

    /**
     * Log the employee out.
     */
    public function logout(): JsonResponse
    {
        auth('employee_api')->logout();

        return $this->success(null, 'Successfully logged out');
    }

    /**
     * Refresh a token.
     */
    public function refresh(): JsonResponse
    {
        return $this->respondWithToken(auth('employee_api')->refresh());
    }

    /**
     * Get the token array structure.
     */
    protected function respondWithToken($token): JsonResponse
    {
        return $this->success([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('employee_api')->factory()->getTTL() * 60,
            'user' => auth('employee_api')->user()
        ], 'Login successful');
    }
}
