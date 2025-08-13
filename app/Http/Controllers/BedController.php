<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\Room;
use App\Models\RoomCategory;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BedController extends Controller
{
    public function create(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'room' => ['required', 'exists:rooms,id'],
        ]);

        try {
            $staffId = Auth::user()->id;

            $room = Room::find($request['room']);

            if (!$room) {
                return response()->json([
                    'message' => 'The room detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // Check if a bed with the same name exists within the room
            $existingBed = Bed::where('name', $request['name'])
                ->where('room_id', $room->id)
                ->first();

            if ($existingBed) {
                return response()->json([
                    'message' => 'Bed with the same name already exists in this room',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $bed = new Bed();
            $bed->name = $request['name'];
            $bed->room_id = $room->id;
            $bed->created_by_id = $staffId;
            $bed->last_updated_by_id = $staffId;
            $bed->save();

            // Return success response
            return response()->json([
                'message' => 'Bed created successfully',
                'status' => 'success',
                'success' => true,
            ], 201);
        } catch (Exception $e) {
            Log::info($e);

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
            $query = $request->input('q');
            $status = $request->input('status');

            $bedQuery = Bed::with(['createdBy', 'lastUpdatedBy', 'room.branch'])
                ->orderBy('updated_at', 'desc');

            if (!empty($query)) {
                $bedQuery->where(
                    'name',
                    'like',
                    "%{$query}%"
                );
            }

            if (!empty($status)) {
                $bedQuery->where('status', $status);
            }

            $beds = $bedQuery->paginate(100);

            return response()->json([
                'message' => 'Bed records fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $beds,
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

    public function findAllAvailableBeds(Request $request)
    {
        try {
            $beds = Bed::with(['createdBy', 'lastUpdatedBy', 'room.branch'])
                ->where('status', 'AVAILABLE')->get();

            return response()->json([
                'message' => 'Bed records fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $beds,
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

    public function dashboardReport()
    {
        try {
            // Fetch all beds with necessary relationships
            $beds = Bed::with(['room', 'createdBy:id,firstname,lastname', 'lastUpdatedBy:id,firstname,lastname', 'assignedPatient:id,firstname,lastname'])->get();

            $totalBeds = $beds->count();

            // Fetch all rooms with necessary relationships
            $rooms = Room::with(['createdBy:id,firstname,lastname', 'lastUpdatedBy:id,firstname,lastname', 'branch:id,name'])->get();

            $totalRooms = $rooms->count();

            $totalRoomCategories = RoomCategory::count();

            $numberOfBedsAvailable = $beds->where('status', 'Available')->count();
            $numberOfDamagedBeds = $beds->where('status', 'Damaged')->count();
            $numberOfBedsOccupied = $beds->where('status', 'Occupied')->count();
            $numberOfRoomsAvailable = $rooms->where('is_available', true)->count();
            $numberOfRoomsOccupied = $totalRooms - $numberOfRoomsAvailable;

            $bedStatusData = DB::table('rooms as room')
                ->leftJoin('room_categories as category', 'room.room_category_id', '=', 'category.id')
                ->leftJoin('beds as bed', 'bed.room_id', '=', 'room.id')
                ->select(
                    'category.name as categoryName',
                    'room.id as roomId',
                    'room.name as roomName',
                    DB::raw("SUM(CASE WHEN bed.status = 'Available' THEN 1 ELSE 0 END) as availableBeds"),
                    DB::raw("SUM(CASE WHEN bed.status = 'Occupied' THEN 1 ELSE 0 END) as occupiedBeds"),
                    DB::raw("SUM(CASE WHEN bed.status = 'Damaged' THEN 1 ELSE 0 END) as damagedBeds")
                )
                ->groupBy('category.id', 'room.id', 'category.name', 'room.name')
                ->orderBy('category.name')
                ->get();

            // Assigned beds with patient and room
            $assignedBeds = Bed::with(['assignedPatient', 'room.branch:id,name'])
                ->whereNotNull('assigned_patient_id')
                ->orderByDesc('created_at')
                ->get();

            // Group bed distribution by category
            $groupedData = [];
            foreach ($bedStatusData as $row) {
                $category = $row->categoryName;

                if (!isset($groupedData[$category])) {
                    $groupedData[$category] = [];
                }

                $groupedData[$category][] = [
                    'available_beds' => (int) $row->availableBeds,
                    'occupied_beds' => (int) $row->occupiedBeds,
                    'damaged_beds' => (int) $row->damagedBeds,
                ];
            }

            return response()->json([
                'message' => 'Facility report detail Fetched Successfully',
                'status' => 'success',
                'success' => true,
                'data' => [
                    'total_beds' => $totalBeds,
                    'total_rooms' => $totalRooms,
                    'total_rooms_available' => $numberOfRoomsAvailable,
                    'total_beds_available' => $numberOfBedsAvailable,
                    'total_beds_occupied' => $numberOfBedsOccupied,
                    'total_damaged_beds' => $numberOfDamagedBeds,
                    'total_rooms_occupied' => $numberOfRoomsOccupied,
                    'total_room_categories' => $totalRoomCategories,
                    'admission_data' => $assignedBeds,
                    'room_categories_distribution' => $groupedData,
                ],
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
}
