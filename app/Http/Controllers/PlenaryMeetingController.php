<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\PlenaryMeeting;
use App\Models\PlenaryMeetingAttendee;
use App\Models\PlenaryMeetingItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PlenaryMeetingController extends Controller
{
    private function checkAdmin($user)
    {
        if ($user->role !== 'admin') {
            return ApiResponse::error('Unauthorized', 403);
        }

        return null;
    }

    public function index(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $perPage = (int) $request->get('per_page', 15);

        $query = PlenaryMeeting::with(['items.item', 'items.cooperative', 'attendees'])
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $query->where('meeting_title', 'like', "%{$request->search}%");
        }

        return ApiResponse::success('Plenary meetings retrieved', $query->paginate($perPage));
    }

    public function listUnpronouncedPlenaryMeetings(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $perPage = (int) $request->get('per_page', 15);

        $query = PlenaryMeeting::with([
            'items' => function ($q) {
                $q->whereDoesntHave('procurementItem')
                  ->with(['item', 'cooperative']);
            },
            'attendees',
        ])
            ->whereHas('items', function ($q) {
                $q->whereDoesntHave('procurementItem');
            })
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $query->where('meeting_title', 'like', '%'.$request->search.'%');
        }

        return ApiResponse::success(
            'Plenary meetings with unpronounced items retrieved',
            $query->paginate($perPage)
        );
    }

    public function show(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $meeting = PlenaryMeeting::with(['items.item', 'items.cooperative', 'attendees'])->find($id);
        if (!$meeting) {
            return ApiResponse::error('Plenary meeting not found', 400);
        }

        return ApiResponse::success('Plenary meeting detail', $meeting);
    }

    public function store(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $validator = Validator::make($request->all(), [
            'meeting_title' => 'required|string|max:255',
            'meeting_date' => 'required|date',
            'meeting_time' => 'nullable|date_format:H:i',
            'location' => 'nullable|string|max:255',
            'chairperson' => 'nullable|string|max:255',
            'secretary' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.cooperative_id' => 'required|exists:cooperatives,id',
            'items.*.cpcl_document_id' => 'nullable|exists:cpcl_documents,id',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.package_quantity' => 'required|integer|min:1',
            'items.*.note' => 'nullable|string|max:255',
            'items.*.location' => 'nullable|string|max:255',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'attendees' => 'nullable|array',
            'attendees.*.name' => 'required|string|max:255',
            'attendees.*.work_unit' => 'required|string|max:255',
            'attendees.*.position' => 'required|string|max:255',
            'attendees.*.signature' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $meeting = PlenaryMeeting::create([
                'meeting_title' => $request->meeting_title,
                'meeting_date' => Carbon::parse($request->meeting_date),
                'meeting_time' => $request->meeting_time,
                'location' => $request->location,
                'chairperson' => $request->chairperson,
                'secretary' => $request->secretary,
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $item) {
                PlenaryMeetingItem::create(array_merge($item, ['plenary_meeting_id' => $meeting->id]));
            }

            if ($request->filled('attendees')) {
                foreach ($request->attendees as $attendee) {
                    PlenaryMeetingAttendee::create(array_merge($attendee, ['plenary_meeting_id' => $meeting->id]));
                }
            }

            DB::commit();

            return ApiResponse::success('Plenary meeting created', $meeting->load('items.item', 'items.cooperative', 'attendees'));
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to create plenary meeting: '.$e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $meeting = PlenaryMeeting::with(['items', 'attendees'])->find($id);
        if (!$meeting) {
            return ApiResponse::error('Plenary meeting not found', 400);
        }

        $validator = Validator::make($request->all(), [
            'meeting_title' => 'required|string|max:255',
            'meeting_date' => 'required|date',
            'meeting_time' => 'nullable|date_format:H:i',
            'location' => 'nullable|string|max:255',
            'chairperson' => 'nullable|string|max:255',
            'secretary' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.cooperative_id' => 'required|exists:cooperatives,id',
            'items.*.cpcl_document_id' => 'nullable|exists:cpcl_documents,id',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.package_quantity' => 'required|integer|min:1',
            'items.*.note' => 'nullable|string|max:255',
            'items.*.location' => 'nullable|string|max:255',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'attendees' => 'nullable|array',
            'attendees.*.name' => 'required|string|max:255',
            'attendees.*.work_unit' => 'required|string|max:255',
            'attendees.*.position' => 'required|string|max:255',
            'attendees.*.signature' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $meeting->update([
                'meeting_title' => $request->meeting_title,
                'meeting_date' => Carbon::parse($request->meeting_date),
                'meeting_time' => $request->meeting_time,
                'location' => $request->location,
                'chairperson' => $request->chairperson,
                'secretary' => $request->secretary,
                'notes' => $request->notes,
            ]);

            $meeting->items()->delete();
            foreach ($request->items as $item) {
                PlenaryMeetingItem::create(array_merge($item, ['plenary_meeting_id' => $meeting->id]));
            }

            $meeting->attendees()->delete();
            if ($request->filled('attendees')) {
                foreach ($request->attendees as $attendee) {
                    PlenaryMeetingAttendee::create(array_merge($attendee, ['plenary_meeting_id' => $meeting->id]));
                }
            }

            DB::commit();

            return ApiResponse::success('Plenary meeting updated', $meeting->load('items.item', 'items.cooperative', 'attendees'));
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to update plenary meeting: '.$e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $meeting = PlenaryMeeting::find($id);
        if (!$meeting) {
            return ApiResponse::error('Plenary meeting not found', 400);
        }

        try {
            DB::beginTransaction();
            $meeting->delete();
            DB::commit();

            return ApiResponse::success('Plenary meeting deleted');
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to delete plenary meeting: '.$e->getMessage(), 500);
        }
    }
}
