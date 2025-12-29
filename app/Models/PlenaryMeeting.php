<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlenaryMeeting extends Model
{
    use HasFactory;

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
}
