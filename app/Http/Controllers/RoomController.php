<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomCategory;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RoomController extends Controller
{
    public function create(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|numeric|exists:room_categories,id',
            'bed_charges' => 'required|numeric',
        ]);

        try {
            $staffId = Auth::user()->id;
            if (Room::where('name', $validatedData['name'])->exists()) {
                return response()->json([
                    'message' => 'Room with the same name already exists',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $room = new Room();
            $room->name = strtoupper(trim($validatedData['name']));
            $room->room_category_id = $validatedData['category'];
            $room->bed_space_charges = $validatedData['bed_charges'];
            $room->created_by_id = $staffId;
            $room->last_updated_by_id = $staffId;
            $room->save();

            return response()->json([
                'message' => 'Room created successfully',
                'status' => 'success',
                'success' => true,
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

    public function findAllWithoutPagination()
    {
        try {
            $rooms = Room::with([
                'category:id,name',
                'createdBy:id,firstname,lastname',
                'lastUpdatedBy:id,firstname,lastname',
                'beds',
                'branch'
            ])
                ->orderBy('updated_at', 'DESC')
                ->get();

            $sortedRooms = $rooms->map(function ($room) {
                return [
                    ...$room->toArray(),
                    'total_beds' => $room->beds->count(),
                    'free_space' => $room->beds->where('status', 'AVAILABLE')->count(),
                ];
            });

            return response()->json([
                'message' => 'All rooms detail fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $sortedRooms,
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

    public function findAllPagination(Request $request)
    {
        try {
            $limit = $request->input('limit', 20);

            $query = Room::with(['createdBy:id,firstname,lastname', 'category:id,name', 'lastUpdatedBy:id,firstname,lastname', 'beds', 'branch', 'category'])
                ->when($request->status && strtoupper($request->status) !== 'ALL', function ($query) use ($request) {
                    $query->where('is_available', strtoupper($request->status) === 'AVAILABLE');
                })
                ->when($request->q, function ($query) use ($request) {
                    $query->where('name', $request->q);
                })
                ->orderBy('updated_at', 'desc');

            $rooms = $query->paginate($limit);

            $sortedRooms = $rooms->getCollection()->map(function ($room) {
                return [
                    ...$room->toArray(),
                    'total_beds' => $room->beds->count(),
                    'free_space' => $room->beds->where('status', 'AVAILABLE')->count(),
                ];
            });

            return response()->json([
                'message' => 'Rooms fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => [
                    'data' => $sortedRooms,
                    'current_page' => $rooms->currentPage(),
                    'per_page' => $rooms->perPage(),
                    'last_page' => $rooms->lastPage(),
                    'total_results' => $rooms->total(),
                    'total_pages' => $rooms->lastPage(),
                    'next_page' => $rooms->hasMorePages() ? $rooms->currentPage() + 1 : null,
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());

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
            $room = Room::with([
                'beds',
                'createdBy:id,firstname,lastname,role',
                'lastUpdatedBy:id,firstname,lastname,role',
            ])->find($id);

            if (!$room) {
                return response()->json([
                    'message' => 'The room detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            return response()->json([
                'message' => 'Room Fetched Successfully',
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
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|integer|exists:room_categories,id',
            'bed_charges' => 'required|numeric',
            // 'branch' => 'required|integer|exists:branches,id',
        ]);
        try {
            $staff = Auth::user();

            // $branch = Branch::find($data['branch']);
            // if (!$branch) {
            //     return response()->json([
            //         'message' => 'The branch detail not found',
            //         'status' => 'error',
            //         'success' => false,
            //     ], 400);
            // }

            $room = Room::find($id);
            if (!$room) {
                return response()->json([
                    'message' => 'The room detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $existingRoom = Room::where('name', $data['name'])
                ->where('id', '!=', $id)
                ->first();
            if ($existingRoom) {
                return response()->json([
                    'message' => 'The name already exists with another room',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $category = RoomCategory::find($request->category);
            if (!$category) {
                return response()->json([
                    'message' => 'The room category detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $room->update([
                'name' => trim($data['name']),
                // 'branch_id' => $branch->id,
                'bed_space_charges' => $data['bed_charges'],
                'room_category_id' => $category->id,
                'last_updated_by' => $staff->id,
            ]);

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

    public function toggleStatus($id, $status)
    {
        try {
            $staffId = Auth::user()->id;

            $room = Room::find($id);

            if (!$room) {
                return response()->json([
                    'message' => 'The room detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if ($room->is_available && $status == 'mark-available') {
                return response()->json([
                    'message' => 'The room is already marked as available',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if (!$room->is_available && $status == 'mark-not-available') {
                return response()->json([
                    'message' => 'The room is already marked as not available',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // Update room status
            $room->last_updated_by_id = $staffId;
            $room->is_available = $status == 'mark-available';
            $room->save();

            return response()->json([
                'message' => 'Room detail status updated successfully',
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

    public function delete($id)
    {
        try {
            $staffId = Auth::user()->id;

            $room = Room::find($id);

            if (!$room) {
                return response()->json([
                    'message' => 'The room account detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $room->last_deleted_by_id = $staffId;
            $room->save();

            // Soft delete the room
            $room->delete();

            return response()->json([
                'message' => 'Room Deleted Successfully',
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
}
