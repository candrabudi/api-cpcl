<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\PlenaryMeeting;
use App\Models\PlenaryMeetingAttendee;
use App\Models\PlenaryMeetingItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PlenaryMeetingController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);

        $query = PlenaryMeeting::query()
            ->withCount([
                'items as total_items',
                'attendees as total_attendees',
            ])
            ->orderByDesc('meeting_date')
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('meeting_title', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('chairperson', 'like', "%{$search}%");
            });
        }

        if ($request->filled('meeting_date')) {
            $query->whereDate('meeting_date', $request->meeting_date);
        }

        $data = $query->paginate($perPage);

        return ApiResponse::success('Plenary meetings retrieved', $data);
    }

    public function show($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid meeting id', 400);
        }

        $meeting = PlenaryMeeting::with([
            'items.cooperative',
            'items.cpclDocument',
            'attendees',
        ])->find($id);

        if (!$meeting) {
            return ApiResponse::error('Plenary meeting not found', 404);
        }

        return ApiResponse::success('Plenary meeting detail', $meeting);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'meeting_title' => 'required|string|max:255',
            'meeting_date' => 'required|date',
            'meeting_time' => 'nullable',
            'location' => 'nullable|string|max:255',
            'chairperson' => 'nullable|string|max:255',
            'secretary' => 'nullable|string|max:255',
            'notes' => 'nullable|string',

            'items' => 'required|array|min:1',
            'items.*.cooperative_id' => 'required|exists:cooperatives,id',
            'items.*.cpcl_document_id' => 'nullable|exists:cpcl_documents,id',
            'items.*.vessel_type' => 'required|string|max:255',
            'items.*.engine_specification' => 'required|string|max:255',
            'items.*.package_quantity' => 'required|integer|min:1',

            'attendees' => 'nullable|array',
            'attendees.*.name' => 'required|string|max:255',
            'attendees.*.work_unit' => 'required|string|max:255',
            'attendees.*.position' => 'required|string|max:255',
            'attendees.*.signature' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $meeting = PlenaryMeeting::create($request->only([
                'meeting_title',
                'meeting_date',
                'meeting_time',
                'location',
                'chairperson',
                'secretary',
                'notes',
            ]));

            foreach ($request->items as $item) {
                PlenaryMeetingItem::create([
                    'plenary_meeting_id' => $meeting->id,
                    'cooperative_id' => $item['cooperative_id'],
                    'cpcl_document_id' => $item['cpcl_document_id'] ?? null,
                    'vessel_type' => $item['vessel_type'],
                    'engine_specification' => $item['engine_specification'],
                    'package_quantity' => $item['package_quantity'],
                ]);
            }

            if ($request->filled('attendees')) {
                foreach ($request->attendees as $attendee) {
                    PlenaryMeetingAttendee::create([
                        'plenary_meeting_id' => $meeting->id,
                        'name' => $attendee['name'],
                        'work_unit' => $attendee['work_unit'],
                        'position' => $attendee['position'],
                        'signature' => $attendee['signature'] ?? null,
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to create plenary meeting '.$e->getMessage(), 500);
        }

        return ApiResponse::success('Plenary meeting created');
    }

    public function update(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid meeting id', 400);
        }

        $validator = Validator::make($request->all(), [
            'meeting_title' => 'required|string|max:255',
            'meeting_date' => 'required|date',
            'meeting_time' => 'nullable',
            'location' => 'nullable|string|max:255',
            'chairperson' => 'nullable|string|max:255',
            'secretary' => 'nullable|string|max:255',
            'notes' => 'nullable|string',

            'items' => 'required|array|min:1',
            'items.*.cooperative_id' => 'required|exists:cooperatives,id',
            'items.*.cpcl_document_id' => 'nullable|exists:cpcl_documents,id',
            'items.*.vessel_type' => 'required|string|max:255',
            'items.*.engine_specification' => 'required|string|max:255',
            'items.*.package_quantity' => 'required|integer|min:1',

            'attendees' => 'nullable|array',
            'attendees.*.name' => 'required|string|max:255',
            'attendees.*.work_unit' => 'required|string|max:255',
            'attendees.*.position' => 'required|string|max:255',
            'attendees.*.signature' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $meeting = PlenaryMeeting::lockForUpdate()->find($id);

            if (!$meeting) {
                DB::rollBack();

                return ApiResponse::error('Plenary meeting not found', 404);
            }

            $meeting->update($request->only([
                'meeting_title',
                'meeting_date',
                'meeting_time',
                'location',
                'chairperson',
                'secretary',
                'notes',
            ]));

            PlenaryMeetingItem::where('plenary_meeting_id', $id)->delete();
            PlenaryMeetingAttendee::where('plenary_meeting_id', $id)->delete();

            foreach ($request->items as $item) {
                PlenaryMeetingItem::create([
                    'plenary_meeting_id' => $id,
                    'cooperative_id' => $item['cooperative_id'],
                    'cpcl_document_id' => $item['cpcl_document_id'] ?? null,
                    'vessel_type' => $item['vessel_type'],
                    'engine_specification' => $item['engine_specification'],
                    'package_quantity' => $item['package_quantity'],
                ]);
            }

            if ($request->filled('attendees')) {
                foreach ($request->attendees as $attendee) {
                    PlenaryMeetingAttendee::create([
                        'plenary_meeting_id' => $id,
                        'name' => $attendee['name'],
                        'work_unit' => $attendee['work_unit'],
                        'position' => $attendee['position'],
                        'signature' => $attendee['signature'] ?? null,
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to update plenary meeting', 500);
        }

        return ApiResponse::success('Plenary meeting updated');
    }

    public function destroy($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid meeting id', 400);
        }

        try {
            DB::beginTransaction();

            $meeting = PlenaryMeeting::find($id);

            if (!$meeting) {
                DB::rollBack();

                return ApiResponse::error('Plenary meeting not found', 404);
            }

            $meeting->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to delete plenary meeting', 500);
        }

        return ApiResponse::success('Plenary meeting deleted');
    }
}
