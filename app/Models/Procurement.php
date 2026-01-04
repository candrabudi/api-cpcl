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

    public function processStatuses()
    {
        return $this->hasOne(ProcurementItemProcessStatus::class, 'id', 'procurement_id');
    }

    public function ProcurementItem()
    {
        return $this->hasOne(ProcurementItem::class, 'id', 'procurement_id');
    }
}
