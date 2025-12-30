<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnnualBudgetTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'annual_budget_id',
        'procurement_item_id',
        'amount',
    ];

    public function annualBudgetAllocation()
    {
        return $this->belongsTo(AnnualBudgetAllocation::class);
    }

    public function procurementItem()
    {
        return $this->belongsTo(ProcurementItem::class);
    }
}
