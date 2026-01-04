<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnualBudget extends Model
{
    protected $fillable = [
        'budget_year',
        'total_budget',
    ];

    protected $appends = ['used_budget', 'remaining_budget'];

    public function getUsedBudgetAttribute()
    {
        return 0;
    }

    public function getRemainingBudgetAttribute()
    {
        return $this->total_budget - $this->used_budget;
    }
}
