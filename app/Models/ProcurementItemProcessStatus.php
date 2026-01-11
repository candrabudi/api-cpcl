<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementItemProcessStatus extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'procurement_item_id',
        'production_attribute_id',
        'status',
        'percentage',
        'production_start_date',
        'production_end_date',
        'area_id',
        'changed_by',
        'notes',
        'status_date',
    ];

    public function procurementItem(): BelongsTo
    {
        return $this->belongsTo(ProcurementItem::class);
    }

    public function productionAttribute(): BelongsTo
    {
        return $this->belongsTo(ProductionAttribute::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
