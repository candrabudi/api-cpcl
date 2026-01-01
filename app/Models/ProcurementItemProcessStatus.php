<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementItemProcessStatus extends Model
{
    protected $fillable = [
        'procurement_item_id',
        'status',
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

    public function Area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
