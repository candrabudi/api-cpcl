<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\ProcurementItem;
use App\Models\ItemTypeBudget;

class AnnualBudget extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'budget_year',
        'total_budget',
        'allocated_budget',
        'used_budget',
        'remaining_budget',
    ];

    public function transactions()
    {
        return $this->hasMany(AnnualBudgetTransaction::class);
    }

    public function procurements()
    {
        return $this->hasMany(Procurement::class);
    }

    public function recalculateBalances()
    {
        $totalAllocated = ItemTypeBudget::where('year', $this->budget_year)->sum('amount');
        $this->allocated_budget = $totalAllocated;
        $this->used_budget = $totalAllocated;
        
        $this->remaining_budget = $this->total_budget - $totalAllocated;
        $this->save();
    }
}
