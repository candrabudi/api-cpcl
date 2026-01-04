<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function procurementItem()
    {
        return $this->hasOne(ProcurementItem::class, 'plenary_meeting_item_id');
    }
}
