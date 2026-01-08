<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnnualBudget extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'budget_year',
        'total_budget',
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
}
