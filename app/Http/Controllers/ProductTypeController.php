<?php

namespace App\Http\Controllers;

use App\Models\ProductType;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProductTypeController extends Controller
{
    public function create(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:product_types,name',
        ]);

        try {
            $staffId = Auth::user()->id;
            $manufacturer = new ProductType();
            $manufacturer->name = $validated['name'];
            $manufacturer->added_by_id = $staffId;
            $manufacturer->last_updated_by_id = $staffId;
            $manufacturer->save();

            return response()->json(['message' => 'Manufacturer created successfully', 'status' => 'succcess', 'success' => true], 201);
        } catch (Exception $th) {
            Log::info($th);

            return response()->json([
                'message' => 'Something went wrong',
                'status' => 'error',
                'success' => false
            ]);
        }
    }

    public function findAll(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 50);
            $query = ProductType::with([
                'products',
                'addedBy.assignedBranch',
                'lastUpdatedBy.assignedBranch',
            ]);

            if ($request->has('q')) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'LIKE', "%{$request->q}%");
                });
            }

            $data = $query->orderBy('updated_at', 'DESC')->paginate($limit);

            return response()->json([
                'message' => 'Product Type fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $data
            ]);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function findOne($id)
    {
        try {
            $productType = ProductType::find($id);
            if (!$productType) {
                return response()->json(['message' => 'Product Type record not found'], 400);
            }

            return response()->json(['message' => 'Product Type record deleted successfully', 'data' => $productType]);
        } catch (Exception $th) {
            Log::info($th);

            return response()->json([
                'message' => 'Something went wrong',
                'status' => 'error',
                'success' => false
            ]);
        }
    }

    public function delete($id)
    {
        try {
            $productType = ProductType::find($id);
            if (!$productType) {
                return response()->json(['message' => 'Product Type record not found'], 400);
            }

            $productType->deleted_by_id = Auth::user()->id;
            $productType->save();
            $productType->delete();

            return response()->json(['message' => 'Product Type record deleted successfully']);
        } catch (Exception $th) {
            Log::info($th);

            return response()->json([
                'message' => 'Something went wrong',
                'status' => 'error',
                'success' => false
            ]);
        }
    }

    public function update(Request $request, $id)
    {

        $validated = $request->validate([
            'name' => 'required|string|unique:product_types,name,' . $id,
        ]);

        try {
            $type = ProductType::find($id);
            if (!$type) {
                return response()->json(['message' => 'type not found'], 400);
            }

            $type->name = $validated['name'];
            $type->last_updated_by_id = Auth::user()->id;
            $type->save();

            return response()->json(['message' => 'Manufacturer updated successfully', 'data' => $type]);
        } catch (Exception $th) {
            Log::info($th);

            return response()->json([
                'message' => 'Something went wrong',
                'status' => 'error',
                'success' => false
            ]);
        }
    }
}
