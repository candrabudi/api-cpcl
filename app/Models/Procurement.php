<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Procurement extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'vendor_id',
        'cooperative_id',
        'procurement_number',
        'procurement_date',
        'status',
        'notes',
        'annual_budget_id',
        'created_by',
    ];

    protected $appends = ['production_progress'];

    public function getProductionProgressAttribute()
    {
        $items = $this->items()->with(['processStatuses' => function($q) {
            $q->orderBy('id', 'desc');
        }])->get();

        $productionItems = $items->filter(function($item) {
            return $item->plenaryMeetingItem?->item?->process_type === 'production';
        });

        if ($productionItems->isEmpty()) {
            return 0;
        }

        $totalProgress = $productionItems->sum(function($item) {
            return $item->processStatuses->first()?->percentage ?? 0;
        });

        return round($totalProgress / $productionItems->count(), 2);
    }

    public function items()
    {
        return $this->hasMany(ProcurementItem::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    public function annualBudget()
    {
        return $this->belongsTo(AnnualBudget::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs()
    {
        return $this->hasMany(ProcurementLog::class);
    }

    protected static function booted()
    {
        static::creating(function ($procurement) {
            if (Auth::check() && !$procurement->created_by) {
                $procurement->created_by = Auth::id();
            }
        });

        static::created(function ($procurement) {
            if (Auth::check()) {
                ProcurementLog::create([
                    'procurement_id' => $procurement->id,
                    'user_id' => Auth::id(),
                    'action' => 'created',
                    'changes' => $procurement->getAttributes(),
                ]);
            }
        });

        static::updating(function ($procurement) {
            if (Auth::check()) {
                $changes = [];
                foreach ($procurement->getDirty() as $field => $newValue) {
                    $changes[$field] = [
                        'old' => $procurement->getOriginal($field),
                        'new' => $newValue,
                    ];
                }

                if (!empty($changes)) {
                    ProcurementLog::create([
                        'procurement_id' => $procurement->id,
                        'user_id' => Auth::id(),
                        'action' => 'updated',
                        'changes' => $changes,
                    ]);
                }
            }
        });

        static::deleted(function ($procurement) {
            if (Auth::check()) {
                ProcurementLog::create([
                    'procurement_id' => $procurement->id,
                    'user_id' => Auth::id(),
                    'action' => 'deleted',
                    'changes' => $procurement->getAttributes(),
                ]);
            }
        });
    }
}
