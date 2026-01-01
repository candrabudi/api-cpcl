<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlenaryMeetingAttendee extends Model
{
    protected $fillable = [
        'plenary_meeting_id',
        'name',
        'work_unit',
        'position',
        'signature',
    ];

    public function meeting()
    {
        return $this->belongsTo(PlenaryMeeting::class, 'plenary_meeting_id');
    }
}
