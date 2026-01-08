<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnualBudgetLog extends Model
{
    protected $fillable = [
        'annual_budget_id',
        'item_type_budget_id',
        'user_id',
        'action',
        'amount',
        'changes',
        'notes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function annualBudget()
    {
        return $this->belongsTo(AnnualBudget::class);
    }

    public function itemTypeBudget()
    {
        return $this->belongsTo(ItemTypeBudget::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
