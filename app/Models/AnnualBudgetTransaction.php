<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnnualBudgetTransaction extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'annual_budget_id',
        'item_type_budget_id',
        'procurement_item_id',
        'type',
        'amount',
        'notes',
    ];

    protected static function booted()
    {
        static::created(function ($transaction) {
            $budget = $transaction->budget;
            if (!$budget) return;

            if ($transaction->type === 'allocation') {
                $budget->increment('allocated_budget', $transaction->amount);
                $budget->decrement('remaining_budget', $transaction->amount);
            } else {
                // Type: spending
                $budget->increment('used_budget', $transaction->amount);
                
                $itemTypeBudget = $transaction->itemTypeBudget;
                if ($itemTypeBudget) {
                    $itemTypeBudget->increment('used_amount', $transaction->amount);
                }
            }
        });

        static::deleted(function ($transaction) {
            $budget = $transaction->budget;
            if (!$budget) return;

            if ($transaction->type === 'allocation') {
                $budget->decrement('allocated_budget', $transaction->amount);
                $budget->increment('remaining_budget', $transaction->amount);
            } else {
                // Type: spending
                $budget->decrement('used_budget', $transaction->amount);

                $itemTypeBudget = $transaction->itemTypeBudget;
                if ($itemTypeBudget) {
                    $itemTypeBudget->decrement('used_amount', $transaction->amount);
                }
            }
        });
    }

    public function budget()
    {
        return $this->belongsTo(AnnualBudget::class, 'annual_budget_id');
    }

    public function itemTypeBudget()
    {
        return $this->belongsTo(ItemTypeBudget::class);
    }

    public function procurementItem()
    {
        return $this->belongsTo(ProcurementItem::class);
    }
}
