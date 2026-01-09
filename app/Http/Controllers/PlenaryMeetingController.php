<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\PlenaryMeeting;
use App\Models\PlenaryMeetingAttendee;
use App\Models\PlenaryMeetingItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PlenaryMeetingController extends Controller
{
    /**
     * Check if user is admin/superadmin
     * SECURITY: Only admin can manage plenary meetings
     */
    private function checkAdmin($user): ?object
    {
        if (!in_array($user->role, ['admin', 'superadmin'])) {
            \Log::warning('Unauthorized plenary meeting access attempt', [
                'user_id' => $user->id,
                'role' => $user->role,
            ]);
            return ApiResponse::error('Unauthorized: Admin access required', 403);
        }
        return null;
    }

    /**
     * List all plenary meetings
     * SECURITY: Admin only
     */
    public function index(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $perPage = min((int) $request->get('per_page', 15), 100); // Max 100
        
        $query = PlenaryMeeting::with([
            'items.item', 
            'items.cooperative', 
            'attendees', 
            'creator'
        ])->orderByDesc('id');

        // Archive Filter
        if ($request->get('filter') === 'archived') {
            $query->onlyTrashed();
        } elseif ($request->get('show_archived') === 'true') {
            $query->withTrashed();
        }

        // Search filter
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where('meeting_title', 'like', "%{$search}%");
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->where('meeting_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('meeting_date', '<=', $request->date_to);
        }

        return ApiResponse::success('Plenary meetings retrieved', $query->paginate($perPage));
    }

    /**
     * List plenary meetings with unprocured items
     * SECURITY: Admin only
     * USE CASE: For creating new procurements
     */
    public function listUnpronouncedPlenaryMeetings(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $perPage = min((int) $request->get('per_page', 15), 100);

        // Get plenary meetings that have at least 1 item not yet in procurement
        $query = PlenaryMeeting::with([
            'items' => function ($q) {
                $q->doesntHave('procurementItem')
                  ->with(['item', 'cooperative']);
            },
            'attendees',
        ])
            ->whereHas('items', function ($q) {
                $q->doesntHave('procurementItem');
            })
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where('meeting_title', 'like', "%{$search}%");
        }

        return ApiResponse::success(
            'Plenary meetings with unprocured items retrieved',
            $query->paginate($perPage)
        );
    }

    /**
     * Show plenary meeting detail
     * SECURITY: Admin only
     */
    public function show(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid plenary meeting ID', 400);
        }

        $meeting = PlenaryMeeting::withTrashed()->with([
            'items.item',
            'items.cooperative',
            'items.cpclDocument',
            'items.procurementItem.procurement',
            'attendees',
            'creator',
            'logs.user'
        ])->find($id);

        if (!$meeting) {
            return ApiResponse::error('Plenary meeting not found', 404);
        }

        return ApiResponse::success('Plenary meeting detail', $meeting);
    }

    /**
     * Create new plenary meeting
     * TRANSACTION: Protected multi-table operation
     * SECURITY: Admin only
     */
    public function store(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        // Validation
        $validator = Validator::make($request->all(), [
            'meeting_title' => 'required|string|max:255',
            'meeting_date' => 'required|date',
            'meeting_time' => 'nullable|date_format:H:i',
            'location' => 'nullable|string|max:255',
            'chairperson' => 'nullable|string|max:255',
            'secretary' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.cooperative_id' => 'required|exists:cooperatives,id',
            'items.*.cpcl_document_id' => 'nullable|exists:cpcl_documents,id',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.package_quantity' => 'required|integer|min:1',
            'items.*.note' => 'nullable|string|max:500',
            'items.*.location' => 'nullable|string|max:255',
            'attendees' => 'nullable|array',
            'attendees.*.name' => 'required|string|max:255',
            'attendees.*.work_unit' => 'nullable|string|max:255',
            'attendees.*.position' => 'nullable|string|max:255',
            'attendees.*.signature' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        // TRANSACTION: Create meeting + items + attendees
        try {
            DB::beginTransaction();

            // Create plenary meeting
            $meeting = PlenaryMeeting::create([
                'meeting_title' => $request->meeting_title,
                'meeting_date' => Carbon::parse($request->meeting_date),
                'meeting_time' => $request->meeting_time,
                'location' => $request->location,
                'chairperson' => $request->chairperson,
                'secretary' => $request->secretary,
                'notes' => $request->notes,
                'created_by' => Auth::id(),
            ]);

            // Create meeting items
            foreach ($request->items as $item) {
                PlenaryMeetingItem::create([
                    'plenary_meeting_id' => $meeting->id,
                    'cooperative_id' => $item['cooperative_id'],
                    'cpcl_document_id' => $item['cpcl_document_id'] ?? null,
                    'item_id' => $item['item_id'],
                    'package_quantity' => $item['package_quantity'],
                    'note' => $item['note'] ?? null,
                    'location' => $item['location'] ?? null,
                ]);
            }

            // Create attendees
            if ($request->filled('attendees')) {
                foreach ($request->attendees as $attendee) {
                    PlenaryMeetingAttendee::create([
                        'plenary_meeting_id' => $meeting->id,
                        'name' => $attendee['name'],
                        'work_unit' => $attendee['work_unit'] ?? null,
                        'position' => $attendee['position'] ?? null,
                        'signature' => $attendee['signature'] ?? null,
                    ]);
                }
            }

            DB::commit();

            \Log::info('Plenary meeting created successfully', [
                'meeting_id' => $meeting->id,
                'meeting_title' => $meeting->meeting_title,
                'items_count' => count($request->items),
                'attendees_count' => count($request->attendees ?? []),
                'created_by' => Auth::id(),
            ]);

            return ApiResponse::success(
                'Plenary meeting created',
                $meeting->load('items.item', 'items.cooperative', 'attendees'),
                201
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Failed to create plenary meeting', [
                'meeting_title' => $request->meeting_title,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to create plenary meeting: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update plenary meeting
     * TRANSACTION: Protected multi-table operation
     * SECURITY: Admin only
     * BUSINESS RULE: Cannot delete items already in procurement
     */
    public function update(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid plenary meeting ID', 400);
        }

        $meeting = PlenaryMeeting::with('items.procurementItem')->find($id);
        if (!$meeting) {
            return ApiResponse::error('Plenary meeting not found', 404);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'meeting_title' => 'required|string|max:255',
            'meeting_date' => 'required|date',
            'meeting_time' => 'nullable|date_format:H:i',
            'location' => 'nullable|string|max:255',
            'chairperson' => 'nullable|string|max:255',
            'secretary' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.cooperative_id' => 'required|exists:cooperatives,id',
            'items.*.cpcl_document_id' => 'nullable|exists:cpcl_documents,id',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.package_quantity' => 'required|integer|min:1',
            'items.*.note' => 'nullable|string|max:500',
            'items.*.location' => 'nullable|string|max:255',
            'attendees' => 'nullable|array',
            'attendees.*.name' => 'required|string|max:255',
            'attendees.*.work_unit' => 'nullable|string|max:255',
            'attendees.*.position' => 'nullable|string|max:255',
            'attendees.*.signature' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        // BUSINESS RULE: Check if any items are already in procurement
        $procuredItems = $meeting->items->filter(function ($item) {
            return $item->procurementItem !== null;
        });

        if ($procuredItems->isNotEmpty()) {
            \Log::warning('Attempted to update plenary meeting with procured items', [
                'meeting_id' => $id,
                'procured_items_count' => $procuredItems->count(),
                'user_id' => Auth::id(),
            ]);

            return ApiResponse::error(
                'Cannot update plenary meeting: Some items are already in procurement. Please remove them from procurement first.',
                400
            );
        }

        // TRANSACTION: Update meeting + sync items + sync attendees
        try {
            DB::beginTransaction();

            // Update plenary meeting
            $meeting->update([
                'meeting_title' => $request->meeting_title,
                'meeting_date' => Carbon::parse($request->meeting_date),
                'meeting_time' => $request->meeting_time,
                'location' => $request->location,
                'chairperson' => $request->chairperson,
                'secretary' => $request->secretary,
                'notes' => $request->notes,
            ]);

            // Sync Items (delete all and recreate)
            $meeting->items()->delete();
            foreach ($request->items as $item) {
                PlenaryMeetingItem::create([
                    'plenary_meeting_id' => $meeting->id,
                    'cooperative_id' => $item['cooperative_id'],
                    'cpcl_document_id' => $item['cpcl_document_id'] ?? null,
                    'item_id' => $item['item_id'],
                    'package_quantity' => $item['package_quantity'],
                    'note' => $item['note'] ?? null,
                    'location' => $item['location'] ?? null,
                ]);
            }

            // Sync Attendees
            $meeting->attendees()->delete();
            if ($request->filled('attendees')) {
                foreach ($request->attendees as $attendee) {
                    PlenaryMeetingAttendee::create([
                        'plenary_meeting_id' => $meeting->id,
                        'name' => $attendee['name'],
                        'work_unit' => $attendee['work_unit'] ?? null,
                        'position' => $attendee['position'] ?? null,
                        'signature' => $attendee['signature'] ?? null,
                    ]);
                }
            }

            DB::commit();

            \Log::info('Plenary meeting updated successfully', [
                'meeting_id' => $meeting->id,
                'meeting_title' => $meeting->meeting_title,
                'items_count' => count($request->items),
                'updated_by' => Auth::id(),
            ]);

            return ApiResponse::success(
                'Plenary meeting updated',
                $meeting->load('items.item', 'items.cooperative', 'attendees')
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Failed to update plenary meeting', [
                'meeting_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to update plenary meeting: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete plenary meeting (soft delete)
     * TRANSACTION: Protected delete operation
     * SECURITY: Admin only
     * BUSINESS RULE: Cannot delete if items are in procurement
     */
    public function destroy(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid plenary meeting ID', 400);
        }

        $meeting = PlenaryMeeting::with('items.procurementItem')->find($id);
        if (!$meeting) {
            return ApiResponse::error('Plenary meeting not found', 404);
        }

        // BUSINESS RULE: Check if any items are in procurement
        $procuredItems = $meeting->items->filter(function ($item) {
            return $item->procurementItem !== null;
        });

        if ($procuredItems->isNotEmpty()) {
            \Log::warning('Attempted to delete plenary meeting with procured items', [
                'meeting_id' => $id,
                'procured_items_count' => $procuredItems->count(),
                'user_id' => Auth::id(),
            ]);

            return ApiResponse::error(
                'Cannot delete plenary meeting: Some items are already in procurement',
                400
            );
        }

        // TRANSACTION: Delete meeting
        try {
            DB::beginTransaction();

            $meetingTitle = $meeting->meeting_title;
            $meeting->delete();

            DB::commit();

            \Log::info('Plenary meeting deleted (archived)', [
                'meeting_id' => $id,
                'meeting_title' => $meetingTitle,
                'deleted_by' => $request->user()->id,
            ]);

            return ApiResponse::success('Plenary meeting deleted (archived)');
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Failed to delete plenary meeting', [
                'meeting_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to delete plenary meeting: ' . $e->getMessage(), 500);
        }
    }
}
