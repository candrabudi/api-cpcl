<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlenaryMeetingAttendee extends Model
{
    use HasFactory;

    protected $fillable = [
        'plenary_meeting_id',
        'name',
        'work_unit',
        'position',
        'signature',
    ];

    public function plenaryMeeting()
    {
        return $this->belongsTo(PlenaryMeeting::class);
    }

    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    public function cpclDocument()
    {
        return $this->belongsTo(CpclDocument::class);
    }
}
