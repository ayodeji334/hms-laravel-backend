<?php

namespace App\Http\Controllers;

use App\Enums\ServiceTypes;
use App\Models\LabTestResultTemplate;
use App\Models\Service;
use App\Models\ServiceCategory;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function store(Request $request)
    {
        DB::beginTransaction();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'type' => 'required|string|max:255',
            'category' => 'nullable|array',
            'categories.*' => 'integer|exists:service_categories,id',
            'result_template' => 'nullable|integer|exists:lab_test_result_templates,id',
        ]);

        try {
            $staff = Auth::user();

            if (Service::where('name', $data['name'])->exists()) {
                return response()->json(['message' => 'Service with the same name already exists'], 400);
            }

            $categories = [];
            if (!empty($data['categories'])) {
                $categories = ServiceCategory::whereIn('id', $data['categories'])->get();

                // Validate if all categories are found
                if ($categories->count() !== count($data['categories'])) {
                    return response()->json(['message' => 'One or more selected categories cannot be found.'], 400);
                }
            }

            $resultTemplate = null;
            if (!empty($data['result_template'])) {
                $resultTemplate = LabTestResultTemplate::find($data['result_template']);

                if (!$resultTemplate) {
                    return response()->json(['message' => 'Result Template not found'], 400);
                }
            }

            $service = new Service();
            $service->name =  trim($data['name']);
            $service->price =  (string) $data['price'];
            $service->is_available =  true;
            $service->type = $data['type'];
            $service->created_by_id =  $staff->id;
            $service->last_updated_by_id = $staff->id;
            $service->result_template_id =  $resultTemplate ? $resultTemplate->id : null;
            $service->save();

            if (!empty($categories)) {
                $service->categories()->attach($categories->pluck('id'));
            }

            DB::commit();

            return response()->json([
                'message' => 'Service created successfully',
                'status'  => 'success',
                'success' => true,
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            Log::info($e->getMessage());

            return response()->json([
                'message' => $e->getMessage() ?? 'Something went wrong. Try again',
                'status'  => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function update(string $id, Request $request)
    {
        $staff = Auth::user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'type' => ['required', Rule::enum(ServiceTypes::class)],
            'categories' => 'nullable|array',
            'categories.*' => 'integer|exists:service_categories,id',
            'result_template' => 'nullable|integer|exists:lab_test_result_templates,id',
        ]);

        try {
            if (empty(array_filter($data))) {
                return response()->json([
                    'message' => 'The Service detail is missing. Please check and try again',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $service = Service::find($id);

            if (!$service) {
                return response()->json([
                    'message' => 'The Service detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if (!empty($data['name'])) {
                $existing = Service::where('name', $data['name'])
                    ->where('id', '!=', $service->id)
                    ->first();

                if ($existing) {
                    return response()->json([
                        'message' => 'The name already exists with another service',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }
            }

            $categories = [];
            if (!empty($data['categories']) && is_array($data['categories'])) {
                $categories = ServiceCategory::whereIn('id', $data['categories'])->get();

                if (
                    $categories->count() === 0 ||
                    $categories->count() !== count($data['categories'])
                ) {
                    return response()->json([
                        'message' => 'One or more selected detail cannot be found. Check and try again',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }
            }

            $resultTemplate = null;
            if (!empty($data['result_template'])) {
                $resultTemplate = LabTestResultTemplate::find($data['result_template']);
                if (!$resultTemplate) {
                    return response()->json([
                        'message' => 'Result Template not found',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }
            }

            $service->update([
                'name' => trim($data['name']),
                'type' => trim($data['type']),
                'price' => number_format((float)$data['price'], 2, '.', ''),
                'last_updated_by_id' => $staff->id,
            ]);

            if (!empty($categories)) {
                $service->categories()->sync($categories->pluck('id'));
            }

            if ($resultTemplate) {
                $service->result_template_id = $resultTemplate->id;
                $service->save();
            }

            return response()->json([
                'message' => 'Service detail updated successfully',
                'success' => true,
                'status' => 'success',
            ]);
        } catch (Exception $e) {
            Log::error('Error updating service: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function delete(string $id)
    {
        try {
            $service = Service::find($id);

            if (!$service) {
                return response()->json([
                    'message' => 'The Service detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $service->delete();

            return response()->json([
                'message' => 'Service detail deleted successfully',
                'success' => true,
                'status' => 'success',
            ]);
        } catch (Exception $e) {
            Log::error('Error updating service: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function findAll(Request $request)
    {
        try {
            $queryBuilder = Service::with(['categories', 'lastUpdatedBy', 'createdBy'])->orderBy("created_at", "desc");
            $query = $request->get('q', '');
            $type = $request->get('type', '');
            $status = $request->get('status', '');

            if (!empty($query)) {
                $queryBuilder->where('name', $query);
            }

            if (!empty($status)) {
                $queryBuilder->where('is_available', strtoupper($status) === "AVAILABLE");
            }

            if (!empty($type)) {
                $queryBuilder->where('type', $type);
            }

            $services = $queryBuilder->paginate(80);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Services retrieved successfully',
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

    public function getAllTests()
    {
        try {
            $services = Service::with(['categories', 'lastUpdatedBy', 'createdBy', 'resultTemplate'])
                ->whereIn('type', [ServiceTypes::LAB_TEST, ServiceTypes::RADIOLOGY_TEST])
                ->where("is_available", true)
                ->orderBy('created_at', 'desc')
                ->paginate(1);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Lab Tests Services retrieved successfully',
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

    public function searchTests(Request $request)
    {
        try {
            $search = $request->q;

            Log::info($search);

            $query = Service::whereIn('type', [ServiceTypes::LAB_TEST, ServiceTypes::RADIOLOGY_TEST])
                ->where("is_available", true);

            $services =  $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            })->get();

            return response()->json([
                'success'   => true,
                'status'    => 'success',
                'message'   => 'Available Tests Services retrieved successfully',
                'data'      => $services
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'An error occurred while retrieving services',
            ], 500);
        }
    }

    public function getAllLabTests()
    {
        try {
            $services = Service::with(['categories', 'lastUpdatedBy', 'createdBy', 'resultTemplate'])
                ->whereIn('type', [ServiceTypes::LAB_TEST])
                ->orderBy('created_at', 'desc')
                ->paginate();

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Lab Tests Services retrieved successfully',
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

    public function getAllRadiologyTests()
    {
        try {
            $services = Service::with(['categories', 'lastUpdatedBy', 'createdBy', 'resultTemplate'])
                ->whereIn('type', [ServiceTypes::RADIOLOGY_TEST])
                ->orderBy('created_at', 'desc')
                ->paginate();

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Lab Tests Services retrieved successfully',
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
