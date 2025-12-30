<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Procurement extends Model
{
    protected $fillable = [
        'annual_budget_id',
        'plenary_meeting_id',
        'procurement_number',
        'procurement_date',
        'status',
        'notes',
    ];

    public function plenaryMeeting()
    {
        return $this->belongsTo(PlenaryMeeting::class);
    }

    public function annualBudget()
    {
        return $this->hasOne(AnnualBudget::class, 'id', 'annual_budget_id');
    }

    public function items()
    {
        return $this->hasMany(ProcurementItem::class);
    }
}
