<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BranchController extends Controller
{
    public function create(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:180',
            'email' => 'required|email|max:200',
            'contact_address' => 'required|string|max:255',
            'emergency_number' => 'required|string|max:15',
        ]);

        try {
            $staff = Auth::user();

            $existingBranch = Branch::where('name', $data['name'])->first();
            if ($existingBranch) {
                return response()->json([
                    'message' => 'Branch with the same name already exists',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $branch = new Branch();
            $branch->name = $request->name;
            $branch->emergency_number = $request->emergency_number;
            $branch->email = $request->email;
            $branch->contact_address = $request->contact_address;
            $branch->created_by_id = $staff->id;
            $branch->last_updated_by_id = $staff->id;
            $branch->save();

            return response()->json([
                'message' => 'Branch created successfully',
                'status' => 'success',
                'success' => true,
                'data' => $branch,
            ], 200);
        } catch (Exception $exception) {
            Log::error($exception->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function findAll(Request $request)
    {
        try {
            $query = $request->query();
            $perPage = isset($query['limit']) ? (int)$query['limit'] : 50;

            $branchQuery = Branch::query()
                ->with(['createdBy', 'lastUpdatedBy'])
                ->orderBy('updated_at', 'desc');

            if (!empty($query['q'])) {
                $branchQuery->where('name', 'LIKE', '%' . $query['q'] . '%');
            }

            $branches = $branchQuery->get();

            return response()->json([
                'message' => 'Branch records fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $branches,
            ], 200);
        } catch (Exception $exception) {
            Log::error($exception->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function findAllWithoutPagination(Request $request)
    {
        try {
            $query = $request->query();
            $perPage = isset($query['limit']) ? (int)$query['limit'] : 50;

            $branchQuery = Branch::query()
                ->with(['createdBy', 'lastUpdatedBy'])
                ->orderBy('updated_at', 'desc');

            if (!empty($query['q'])) {
                $branchQuery->where('name', 'LIKE', '%' . $query['q'] . '%');
            }

            $branches = $branchQuery->get();

            return response()->json([
                'message' => 'Branch records fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $branches,
            ], 200);
        } catch (Exception $exception) {
            Log::error($exception->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function findOne(string $id)
    {
        try {
            $branch = Branch::with(['createdBy:id,firstname,lastname,role', 'lastUpdatedBy:id,firstname,lastname,role'])
                ->find($id);

            if (!$branch) {
                return response()->json([
                    'message' => 'The branch detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            return response()->json([
                'message' => 'Branch fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $branch,
            ], 200);
        } catch (Exception $exception) {
            Log::error($exception->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:180',
            'email' => 'required|email|max:200',
            'contact_address' => 'required|string|max:255',
            'emergency_number' => 'required|string|max:15',
        ]);

        try {
            $branch = Branch::find($id);

            if (!$branch) {
                return response()->json([
                    'message' => 'The branch detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // Check if branch with the same name exists
            $branchWithSameName = Branch::where('name', strtoupper($request->name))
                ->where('id', '!=', $id)
                ->first();

            if ($branchWithSameName) {
                return response()->json([
                    'message' => 'The name already exists with another branch',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // Update branch details
            $branch->name = $request->name;
            $branch->emergency_number = $request->emergency_number;
            $branch->email = $request->email;
            $branch->contact_address = $request->contact_address;
            $branch->last_updated_by_id = Auth::user()->id;
            $branch->save();

            return response()->json([
                'message' => 'Branch detail updated successfully',
                'status' => 'success',
                'success' => true,
            ], 200);
        } catch (Exception $exception) {
            Log::error($exception->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $branch = Branch::find($id);

            if (!$branch) {
                return response()->json([
                    'message' => 'The branch detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $branch->last_deleted_by_id = Auth::user()->id;
            $branch->save();
            $branch->delete();

            return response()->json([
                'message' => 'Branch deleted successfully',
                'status' => 'success',
                'success' => true,
            ], 200);
        } catch (Exception $exception) {
            Log::error($exception->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }
}
