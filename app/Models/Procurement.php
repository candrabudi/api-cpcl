<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Procurement extends Model
{
    protected $fillable = [
        'plenary_meeting_id',
        'procurement_number',
        'procurement_date',
        'status',
        'notes',
    ];

    public function items()
    {
        return $this->hasMany(ProcurementItem::class);
    }

    public function plenaryMeeting()
    {
        return $this->belongsTo(PlenaryMeeting::class);
    }
}
