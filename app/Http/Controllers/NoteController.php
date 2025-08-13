<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NoteController extends Controller
{
    public function create($createNoteDto)
    {
        try {
            $staffid = Auth::user()->id;

            $note = new Note();
            $note->content = $createNoteDto['content'];
            $note->title = $createNoteDto['title'];
            $note->created_by_id = $staffid;
            $note->save();
            return $note;
        } catch (Exception $e) {
            Log::error('Error creating note', ['error' => $e->getMessage()]);

            throw new Exception('Something went wrong while creating the note');
        }
    }

    public function update($id, Request $request)
    {
        $validatedData = $request->validate([
            'content' => 'required|string',
            'title' => 'required|string',
        ]);

        try {
            // Check if the note exists
            $note = Note::with('createdBy')->find($id);

            if (!$note) {
                return response()->json([
                    'message' => 'The note detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            // Check if the current user is the author of the note
            if ($note->createdBy->id !== Auth::user()->id) {
                return response()->json([
                    'message' => 'You can only edit the note you created',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            // Update the note
            $note->content = $validatedData['content'];
            $note->title = $validatedData['title'];
            $note->last_updated_by = Auth::user()->id;
            $note->save();

            return response()->json([
                'message' => 'Note detail updated successfully',
                'status' => 'success',
                'success' => true,
            ], 200);
        } catch (Exception $e) {
            // Log the error and return a response
            Log::error('Error updating note', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function remove($id)
    {
        try {
            $note = Note::find($id);

            if (!$note) {
                return response()->json([
                    'message' => 'The note detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $note->delete();

            return response()->json([
                'message' => 'Note Deleted Successfully',
                'status' => 'success',
                'success' => true,
            ], 200);
        } catch (Exception $e) {
            Log::error('Error deleting note', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }
}
