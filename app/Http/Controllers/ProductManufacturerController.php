<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductManufacturer;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProductManufacturerController extends Controller
{
    public function create(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:product_manufacturers,name',
            'email' => 'nullable|email',
            'phone_number' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        try {
            $staffId = Auth::user()->id;
            $manufacturer = new ProductManufacturer();
            $manufacturer->name = $validated['name'];
            $manufacturer->email = $validated['email'];
            $manufacturer->email = $validated['hone_number'];
            $manufacturer->email = $validated['address'];
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
            $query = ProductManufacturer::query();

            if ($request->has('q')) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'LIKE', "%{$request->q}%")
                        ->orWhere('email', 'LIKE', "%{$request->q}%")
                        ->orWhere('phone_number', 'LIKE', "%{$request->q}%");
                });
            }

            $manufacturers = $query->orderByDesc('updated_at')->paginate($request->input('limit', 10));

            return response()->json(['message' => 'Manufacturers fetched successfully', 'data' => $manufacturers]);
        } catch (Exception $th) {
            Log::info($th);

            return response()->json([
                'message' => 'Something went wrong',
                'status' => 'error',
                'success' => false
            ]);
        }
    }

    public function findOne($id)
    {
        try {
            $manufacturer = ProductManufacturer::find($id);

            if (!$manufacturer) {
                return response()->json(['message' => 'Manufacturer not found'], 400);
            }

            return response()->json(['message' => 'Manufacturer fetched successfully', 'data' => $manufacturer]);
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
            'name' => 'required|string|unique:product_manufacturers,name,' . $id,
            'email' => 'nullable|email',
            'phone_number' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        try {
            $manufacturer = ProductManufacturer::find($id);
            if (!$manufacturer) {
                return response()->json(['message' => 'Manufacturer not found'], 400);
            }

            $manufacturer->name = $validated['name'];
            $manufacturer->email = $validated['email'];
            $manufacturer->email = $validated['hone_number'];
            $manufacturer->email = $validated['address'];
            $manufacturer->last_updated_by_id = Auth::user()->id;
            $manufacturer->save();

            return response()->json(['message' => 'Manufacturer updated successfully', 'data' => $manufacturer]);
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
            $manufacturer = ProductManufacturer::find($id);
            if (!$manufacturer) {
                return response()->json(['message' => 'Manufacturer not found'], 400);
            }

            $manufacturer->deleted_by_id = Auth::user()->id;
            $manufacturer->save();
            $manufacturer->delete();

            return response()->json(['message' => 'Manufacturer deleted successfully']);
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
