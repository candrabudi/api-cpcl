<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PlenaryMeeting extends Model
{
    protected $fillable = [
        'meeting_title',
        'meeting_date',
        'meeting_time',
        'location',
        'chairperson',
        'secretary',
        'notes',
    ];

    public function items()
    {
        return $this->hasMany(PlenaryMeetingItem::class);
    }

    public function attendees()
    {
        return $this->hasMany(PlenaryMeetingAttendee::class);
    }

    protected static function booted()
    {
        static::creating(function ($meeting) {
            if (Auth::check()) {
                $meeting->created_by = Auth::id();
            }
        });

        static::created(function ($meeting) {
            if (Auth::check()) {
                PlenaryMeetingLog::create([
                    'plenary_meeting_id' => $meeting->id,
                    'user_id' => Auth::id(),
                    'action' => 'created',
                    'changes' => $meeting->getAttributes(),
                ]);
            }
        });

        static::updating(function ($meeting) {
            if (Auth::check()) {
                $changes = [];
                foreach ($meeting->getDirty() as $field => $newValue) {
                    $changes[$field] = [
                        'old' => $meeting->getOriginal($field),
                        'new' => $newValue,
                    ];
                }

                if (!empty($changes)) {
                    PlenaryMeetingLog::create([
                        'plenary_meeting_id' => $meeting->id,
                        'user_id' => Auth::id(),
                        'action' => 'updated',
                        'changes' => $changes,
                    ]);
                }
            }
        });

        static::deleted(function ($meeting) {
            if (Auth::check()) {
                PlenaryMeetingLog::create([
                    'plenary_meeting_id' => $meeting->id,
                    'user_id' => Auth::id(),
                    'action' => 'deleted',
                    'changes' => $meeting->getAttributes(),
                ]);
            }
        });
    }

    public function logs()
    {
        return $this->hasMany(PlenaryMeetingLog::class, 'plenary_meeting_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
