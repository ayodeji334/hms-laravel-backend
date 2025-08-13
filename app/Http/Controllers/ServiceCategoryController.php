<?php

namespace App\Http\Controllers;

use App\Models\ServiceCategory;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ServiceCategoryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string']);

        try {
            $staff = Auth::user();

            // Check if the Service with the same name exists
            if (ServiceCategory::where('name', $data['name'])->exists()) {
                return response()->json(['message' => 'Service Category with the same name already exists'], 400);
            }

            $serviceCategory = new ServiceCategory();
            $serviceCategory->name =  trim($data['name']);
            $serviceCategory->created_by_id = $staff->id;
            $serviceCategory->last_updated_by_id = $staff->id;
            $serviceCategory->save();

            return response()->json([
                'message' => 'Service Category created successfully',
                'status'  => 'success',
                'success' => true,
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status'  => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function findAll(Request $request)
    {
        try {
            $queryBuilder = ServiceCategory::with(['createdBy', 'lastUpdatedBy']);
            $query = $request->get('q', '');

            if (!empty($query)) {
                $queryBuilder->where('name', $query);
            }

            $services = $queryBuilder->orderBy("created_at", "desc")->paginate(20);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Services categories retrieved successfully',
                'data' => $services
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            // Return the error message for debugging
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'An error occurred while retrieving service',
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function getAllWithoutPagination(Request $request)
    {
        try {
            $queryBuilder = ServiceCategory::with(['createdBy', 'lastUpdatedBy']);
            $query = $request->get('q', '');

            if (!empty($query)) {
                $queryBuilder->where('name', $query);
            }

            $services = $queryBuilder->get();

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Services categories retrieved successfully',
                'data' => $services
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            // Return the error message for debugging
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'An error occurred while retrieving service',
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
