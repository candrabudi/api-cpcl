<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Procurement extends Model
{
    protected $fillable = [
        'plenary_meeting_id',
        'annual_budget_allocation_id',
        'procurement_number',
        'procurement_date',
        'status',
        'notes',
    ];

    public function plenaryMeeting()
    {
        return $this->belongsTo(PlenaryMeeting::class);
    }

    public function allocation()
    {
        return $this->belongsTo(AnnualBudgetAllocation::class);
    }

    public function items()
    {
        return $this->hasMany(ProcurementItem::class);
    }
}
