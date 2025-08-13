<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function staffLogin(Request $request)
    {
        $credentials = $request->validate([
            "staff_id" => "required",
            "password" => "required",
        ], [
            "staff_id.required" => "The staff ID field is required.",
            "password.required" => "The password field is required.",
        ]);

        try {
            $staff = User::where('staff_number', $credentials['staff_id'])->first();

            if (!$staff || !Hash::check($credentials['password'], $staff->password)) {
                return response([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Invalid credentials'
                ], 400);
            }

            /** @var User $staff */

            // generate token
            $authToken = $staff->createToken('main')->plainTextToken;
            $staff->load('assignedBranch');

            return response([
                'success' => true,
                'status' => 'success',
                'message' => 'Login successsfully',
                'data' => [
                    'token' => $authToken,
                    'user' => $staff
                ]
            ]);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            return response()->json([
                'message' => 'An error occurred while trying to delete a course registration',
                'success' => false,
                "status" => "error"
            ], 500);
        }
    }

    public function updateStaffPassword(Request $request)
    {
        $credentials = $request->validate([
            "current_password" => "required",
            "new_password" => "required",
        ], [
            "current_password.required" => "The current password field is required.",
            "new_password.required" => "The new password field is required.",
        ]);

        try {
            $staff = Auth::user();

            if (!$staff || !Hash::check($credentials['current_password'], $staff->password)) {
                return response([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Current Password does not match. Please try again later'
                ], 400);
            }

            $new_password = Hash::make($credentials['new_password']);

            /** @var User $staff */
            $staff->password = $new_password;
            $staff->save();

            return response([
                'success' => true,
                'status' => 'success',
                'message' => 'Password updated successsfully',
            ]);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            return response()->json([
                'message' => 'An error occurred while trying to delete a course registration',
                'success' => false,
                "status" => "error"
            ], 500);
        }
    }
}
