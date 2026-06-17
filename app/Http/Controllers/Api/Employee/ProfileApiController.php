<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Storage;

class ProfileApiController extends ApiController
{
    /**
     * Change User Password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return $this->error('Validation Error', 422, $validator->errors());
        }

        $user = auth('api')->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->error('Current password does not match nuestro record.', 422, [
                'current_password' => ['The current password provided is incorrect.']
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return $this->success($user, 'Password updated successfully.');
    }

    /**
     * Update Profile Data
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $request->validate([
            'username' => 'nullable|string|max:255|unique:users,username,' . $user->id,
            'email' => 'nullable|email|max:255|unique:users,email,' . $user->id,

            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'personal_email' => 'nullable|email|max:255',
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string',

            'avatar' => 'nullable|string|starts_with:temp/',
        ]);

        // Update users table
        $user->update(
            $request->only('username', 'email')
        );

        $employee = $user->employee;

        if ($employee) {
            if(!$request->personal_email){
                return $this->error('Personal email is required for employee', 422);
            }

            // Update employee table
            $employee->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'personal_email' => $request->personal_email,
                'personal_number' => $request->personal_number,
                'address' => $request->address,
            ]);



            // Avatar upload
            if ($request->hasFile('avatar')) {

                if ($employee->avatar) {
                    Storage::disk('public')->delete($employee->avatar);
                }

                $path = $request->file('avatar')->store('avatars', 'public');

                $employee->update([
                    'avatar' => $path
                ]);
            } elseif (
                $request->filled('avatar') &&
                str_starts_with($request->input('avatar'), 'temp/')
            ) {

                $tempPath = $request->input('avatar');

                if (Storage::disk('public')->exists($tempPath)) {

                    if ($employee->avatar) {
                        Storage::disk('public')->delete($employee->avatar);
                    }

                    $fileName = basename($tempPath);
                    $newPath = 'avatars/' . $fileName;

                    Storage::disk('public')->move($tempPath, $newPath);

                    $employee->update([
                        'avatar' => $newPath
                    ]);
                }
            }
        }

        $user->load('employee');

        return $this->success($user, 'Profile updated successfully.');
    }
}
