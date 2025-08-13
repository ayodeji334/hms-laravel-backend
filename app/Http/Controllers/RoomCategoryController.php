<?php

namespace App\Http\Controllers;

use App\Models\RoomCategory;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RoomCategoryController extends Controller
{
    public function create(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'The name field is required.',
            'name.string' => 'The name must be a valid string.',
            'name.max' => 'The name must not exceed 255 characters.',
        ]);

        try {
            $staff = Auth::user();

            // Check if the room category with the same name exists
            $existingCategory = RoomCategory::where('name', strtoupper(trim($request->name)))->first();

            if ($existingCategory) {
                return response()->json([
                    'message' => 'Room with the same name already exists',
                    'status' => 'error',
                    'success' => false
                ], 400);
            }

            // Create new room category
            $roomCategory = new RoomCategory();
            $roomCategory->name = strtoupper(trim($request->name));
            $roomCategory->created_by_id = $staff->id;
            $roomCategory->last_updated_by_id = $staff->id;
            $roomCategory->save();

            return response()->json([
                'message' => 'Room category created successfully',
                'status' => 'success',
                'success' => true
            ], 201);
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function findAll()
    {
        try {
            $roomCategories = RoomCategory::with([
                'createdBy:id,firstname,lastname,role',
                'lastUpdatedBy:id,firstname,lastname,role',
            ])
                ->orderBy('updated_at', 'desc')
                ->get();

            return response()->json([
                'message' => 'All rooms detail fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $roomCategories
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

    public function findAllWithPagination()
    {
        try {
            $roomCategories = RoomCategory::with([
                'createdBy:id,firstname,lastname,role',
                'lastUpdatedBy:id,firstname,lastname,role',
            ])
                ->orderBy('updated_at', 'desc')
                ->paginate(40);

            return response()->json([
                'message' => 'All rooms detail fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $roomCategories
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

    public function findAllByIDs(Request $request)
    {
        try {
            $categoryIds = $request->input('category_ids');

            if (!is_array($categoryIds) || empty($categoryIds)) {
                return response()->json([
                    'message' => 'The category_ids field is required and must be a non-empty array.',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $categories = RoomCategory::whereIn('id', $categoryIds)->get();

            return response()->json([
                'message' => 'Categories fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $categories,
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
            $room = RoomCategory::with([
                'created_by:id,firstname,lastname,role',
                'last_updated_by:id,firstname,lastname,role'
            ])->find($id);

            if (!$room) {
                return response()->json([
                    'message' => 'The room detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            return response()->json([
                'message' => 'Room fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $room,
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
        $validatedData = $request->validate([
            'name' => 'nullable|string|max:255',
        ], [
            'name.string' => 'The room name must be a valid string.',
        ]);

        try {
            $staff = Auth::user();

            if (empty($validatedData['name'])) {
                return response()->json([
                    'message' => 'The room detail is missing. Please check and try again',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $room = RoomCategory::find($id);

            if (!$room) {
                return response()->json([
                    'message' => 'The room detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $roomWithSameName = RoomCategory::where('name', $validatedData['name'])
                ->where('id', '!=', $room->id)
                ->first();

            if ($roomWithSameName) {
                return response()->json([
                    'message' => 'The name already exists with another room',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $room->name = strtoupper(trim($validatedData['name']));
            $room->last_updated_by_id = $staff->id;
            $room->save();

            return response()->json([
                'message' => 'Room detail updated successfully',
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

    public function delete(string $id)
    {
        try {
            $staff = Auth::user();

            $room = RoomCategory::find($id);

            if (!$room) {
                return response()->json([
                    'message' => 'The room category detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $room->last_deleted_by_id = $staff->id;
            $room->save();

            $room->delete(); // Soft delete the room

            return response()->json([
                'message' => 'Room categroy Deleted Successfully',
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
