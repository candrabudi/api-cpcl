<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlenaryMeetingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'plenary_meeting_id',
        'cooperative_id',
        'vessel_type',
        'engine_specification',
        'package_quantity',
    ];

    public function plenaryMeeting()
    {
        return $this->belongsTo(PlenaryMeeting::class);
    }

    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }
}
