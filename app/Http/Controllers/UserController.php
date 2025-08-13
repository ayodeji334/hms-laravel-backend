<?php

namespace App\Http\Controllers;

use App\Enums\Roles;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function create(Request $request)
    {

        $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'staff_number' => 'required|string|unique:users,staff_number',
            'gender' => 'nullable|string|max:255',
            'marital_status' => 'nullable|string|max:255',
            'religion' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'nationality' => 'required|string|max:255',
            'phone_number' => 'required|string|unique:users,phone_number',
            'branch_id' => 'required|exists:branches,id',
            'user_type' => 'required|in:NURSE,PHARMACIST,DOCTOR,LAB-TECHNOLOGIST,RECORD-KEEPER,CASHIER,RADIOLOGIST',
        ]);

        try {
            $staff = new User();
            $staff->name = $request->firstname . " " . $request->lastname;
            $staff->firstname = $request->firstname;
            $staff->lastname = $request->lastname;
            $staff->email = $request->email;
            $staff->password = Hash::make($request->phone_number);
            $staff->religion = $request->religion;
            $staff->marital_status = $request->marital_status;
            $staff->nationality = $request->nationality;
            $staff->phone_number = $request->phone_number;
            $staff->gender = $request->gender;
            $staff->staff_number = $request->staff_number;
            $staff->branch_id = $request->branch_id;
            $staff->role = $request->user_type;
            $staff->save();

            return response()->json([
                'message' => 'Account created successfully',
                'status' => 'success',
                'success' => true
            ]);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());

            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'An error occurred while creating user',
            ], 500);
        }
    }

    public function findAll(Request $request)
    {
        try {
            $queryBuilder = User::with(['assignedBranch:id,name'])->orderBy("created_at", "desc");
            $query = $request->get('q', '');
            $type = $request->get('type', '');

            if (!empty($type)) {
                $queryBuilder->where('role', $type);
            }

            // Apply search filter if provided
            if (!empty($query)) {
                $queryBuilder->where(function ($qb) use ($query) {
                    $qb->where('firstname', 'like', "%$query%")
                        ->orWhere('lastname', 'like', "%$query%")
                        ->orWhere('email', 'like', "%$query%")
                        ->orWhere('staff_number', 'like', "%$query%");
                });
            }

            // Paginate results
            $patients = $queryBuilder->paginate(8);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Users records retrieved successfully',
                'data' => $patients
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            // Return the error message for debugging
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'An error occurred while retrieving patient records',
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function findDoctors(Request $request)
    {
        try {
            $queryBuilder = User::with(['assignedBranch'])->where('role', Roles::DOCTOR)->orderBy("created_at", "desc");
            $query = $request->get('q', '');

            // Apply search filter if provided
            if (!empty($query)) {
                $queryBuilder->where(function ($qb) use ($query) {
                    $qb->where('firstname', 'like', "%$query%")
                        ->orWhere('lastname', 'like', "%$query%")
                        ->orWhere('email', 'like', "%$query%")
                        ->orWhere('staff_number', 'like', "%$query%");
                });
            }

            // Paginate results
            $patients = $queryBuilder->paginate(8);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Patient records retrieved successfully',
                'data' => $patients
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            // Return the error message for debugging
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'An error occurred while retrieving patient records',
                'trace' => $e->getMessage()
            ], 500);
        }
    }

    public function findDoctorsWithoutPagination()
    {
        try {
            // Paginate results
            $doctors =  User::with(['assignedBranch'])->where('role', Roles::DOCTOR)->get();

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Doctors records retrieved successfully',
                'data' => $doctors
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            // Return the error message for debugging
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'An error occurred while retrieving patient records',
                'trace' => $e->getMessage()
            ], 500);
        }
    }

    public function findNurses(Request $request)
    {
        try {
            $queryBuilder = User::with(['assignedBranch'])->where('role', Roles::NURSE)->orderBy("created_at", "desc");
            $query = $request->get('q', '');

            // Apply search filter if provided
            if (!empty($query)) {
                $queryBuilder->where(function ($qb) use ($query) {
                    $qb->where('firstname', 'like', "%$query%")
                        ->orWhere('lastname', 'like', "%$query%")
                        ->orWhere('email', 'like', "%$query%")
                        ->orWhere('patient_reg_no', 'like', "%$query%");
                });
            }

            // Paginate results
            $patients = $queryBuilder->paginate(8);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Patient records retrieved successfully',
                'data' => $patients
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            // Return the error message for debugging
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'An error occurred while retrieving patient records',
                'trace' => $e->getMessage()
            ], 500);
        }
    }

    public function delete($id)
    {
        try {
            $staff = User::find($id);

            if (!$staff) {
                return response()->json([
                    'message' => 'The staff account detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // Soft delete the staff
            $staff->delete();

            return response()->json([
                'message' => 'Staff account Deleted Successfully',
                'status' => 'success',
                'success' => true,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'staff_number' => [
                'required',
                'string',
                Rule::unique('users', 'staff_number')->ignore($id),
            ],
            'gender' => 'nullable|string|max:255',
            'marital_status' => 'nullable|string|max:255',
            'religion' => 'nullable|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($id),
            ],
            'nationality' => 'required|string|max:255',
            'phone_number' => [
                'required',
                'string',
                Rule::unique('users', 'phone_number')->ignore($id),
            ],
            'branch_id' => 'required|exists:branches,id',
            'user_type' => 'required|in:NURSE,PHARMACIST,DOCTOR,LAB-TECHNOLOGIST,RECORD-KEEPER,CASHIER,RADIOLOGIST',
        ]);

        try {
            $staff = User::find($id);

            if (!$staff) {
                return response()->json([
                    'message' => 'The staff account detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $staff->name = $request->firstname . " " . $request->lastname;
            $staff->firstname = $request->firstname;
            $staff->lastname = $request->lastname;
            $staff->email = $request->email;
            $staff->password = Hash::make($request->phone_number);
            $staff->religion = $request->religion;
            $staff->marital_status = $request->marital_status;
            $staff->nationality = $request->nationality;
            $staff->phone_number = $request->phone_number;
            $staff->gender = $request->gender;
            $staff->staff_number = $request->staff_number;
            $staff->branch_id = $request->branch_id;
            $staff->role = $request->user_type;
            $staff->save();

            return response()->json([
                'message' => 'Staff account updated successfully',
                'status' => 'success',
                'success' => true,
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }
}
