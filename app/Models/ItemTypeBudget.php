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
            $annualBudget = AnnualBudget::firstOrCreate(
                ['budget_year' => $itemTypeBudget->year],
                [
                    'total_budget' => 0,
                    'used_budget' => 0,
                    'allocated_budget' => 0,
                    'remaining_budget' => 0
                ]
            );
            
            if ($annualBudget) {
                // Log for visibility
                AnnualBudgetLog::create([
                    'annual_budget_id' => $annualBudget->id,
                    'item_type_budget_id' => $itemTypeBudget->id,
                    'user_id' => auth()->id(),
                    'action' => 'allocation',
                    'amount' => $itemTypeBudget->amount,
                    'notes' => "Initial allocation for " . optional($itemTypeBudget->itemType)->name,
                    'changes' => $itemTypeBudget->getAttributes()
                ]);

                // Create transaction to deduct from annual budget
                AnnualBudgetTransaction::create([
                    'annual_budget_id' => $annualBudget->id,
                    'item_type_budget_id' => $itemTypeBudget->id,
                    'type' => 'allocation',
                    'amount' => $itemTypeBudget->amount,
                    'notes' => "Allocated to " . optional($itemTypeBudget->itemType)->name,
                ]);
            }
        });

        static::updated(function ($itemTypeBudget) {
            if ($itemTypeBudget->isDirty('amount')) {
                $annualBudget = AnnualBudget::firstOrCreate(
                    ['budget_year' => $itemTypeBudget->year],
                    [
                        'total_budget' => 0,
                        'used_budget' => 0,
                        'allocated_budget' => 0,
                        'remaining_budget' => 0
                    ]
                );
                
                if ($annualBudget) {
                    $oldAmount = $itemTypeBudget->getOriginal('amount');
                    $newAmount = $itemTypeBudget->amount;
                    $diff = $newAmount - $oldAmount;

                    AnnualBudgetLog::create([
                        'annual_budget_id' => $annualBudget->id,
                        'item_type_budget_id' => $itemTypeBudget->id,
                        'user_id' => auth()->id(),
                        'action' => 'adjustment',
                        'amount' => $diff,
                        'notes' => "Budget adjustment for " . optional($itemTypeBudget->itemType)->name,
                        'changes' => [
                            'old' => $oldAmount,
                            'new' => $newAmount
                        ]
                    ]);

                    // Create transaction for the difference
                    AnnualBudgetTransaction::create([
                        'annual_budget_id' => $annualBudget->id,
                        'item_type_budget_id' => $itemTypeBudget->id,
                        'type' => 'allocation',
                        'amount' => $diff,
                        'notes' => "Budget adjustment for " . optional($itemTypeBudget->itemType)->name,
                    ]);
                }
            }
        });
    }
}
