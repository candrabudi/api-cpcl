<?php

namespace App\Models;

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnualBudget extends Model
{
    protected $fillable = [
        'budget_year',
        'total_budget',
        'used_budget',
        'remaining_budget',
    ];

    public function allocations()
    {
        return $this->hasMany(AnnualBudgetAllocation::class);
    }
}
