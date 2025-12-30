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

    protected $appends = ['used_budget', 'remaining_budget'];

    public function procurements()
    {
        return $this->hasMany(Procurement::class);
    }

    public function getUsedBudgetAttribute()
    {
        return $this->procurements()->with('items')->get()->sum(function ($p) {
            return $p->items->sum('total_price');
        });
    }

    public function getRemainingBudgetAttribute()
    {
        return $this->total_budget - $this->used_budget;
    }
}
