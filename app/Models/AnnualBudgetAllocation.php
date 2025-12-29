<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnualBudgetAllocation extends Model
{
    protected $fillable = [
        'annual_budget_id',
        'allocation_name',
        'allocated_amount',
        'used_amount',
        'remaining_amount',
    ];

    public function budget()
    {
        return $this->belongsTo(AnnualBudget::class);
    }

    public function procurements()
    {
        return $this->hasMany(Procurement::class);
    }
}
