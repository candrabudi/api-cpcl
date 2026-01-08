<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemTypeBudget extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $fillable = [
        'item_type_id',
        'year',
        'amount',
        'used_amount',
    ];

    public function itemType()
    {
        return $this->belongsTo(ItemType::class);
    }

    public function annualBudget()
    {
        return $this->belongsTo(AnnualBudget::class, 'year', 'budget_year');
    }

    protected static function booted()
    {
        static::created(function ($itemTypeBudget) {
            $annualBudget = AnnualBudget::where('budget_year', $itemTypeBudget->year)->first();
            if ($annualBudget) {
                AnnualBudgetLog::create([
                    'annual_budget_id' => $annualBudget->id,
                    'item_type_budget_id' => $itemTypeBudget->id,
                    'user_id' => auth()->id(),
                    'action' => 'allocation',
                    'amount' => $itemTypeBudget->amount,
                    'notes' => "Initial allocation for " . optional($itemTypeBudget->itemType)->name,
                    'changes' => $itemTypeBudget->getAttributes()
                ]);
            }
        });

        static::updated(function ($itemTypeBudget) {
            if ($itemTypeBudget->isDirty('amount')) {
                $annualBudget = AnnualBudget::where('budget_year', $itemTypeBudget->year)->first();
                if ($annualBudget) {
                    AnnualBudgetLog::create([
                        'annual_budget_id' => $annualBudget->id,
                        'item_type_budget_id' => $itemTypeBudget->id,
                        'user_id' => auth()->id(),
                        'action' => 'adjustment',
                        'amount' => $itemTypeBudget->amount,
                        'notes' => "Budget adjustment for " . optional($itemTypeBudget->itemType)->name,
                        'changes' => [
                            'old' => $itemTypeBudget->getOriginal('amount'),
                            'new' => $itemTypeBudget->amount
                        ]
                    ]);
                }
            }
        });
    }
}
