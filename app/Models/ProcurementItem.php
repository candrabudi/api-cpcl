<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementItem extends Model
{
    protected $fillable = [
        'procurement_id',
        'plenary_meeting_item_id',
        'vendor_id',
        'quantity',
        'unit_price',
        'total_price',
        'delivery_status',
        'estimated_delivery_date',
        'actual_delivery_date',
        'received_at',
    ];

    public function procurement()
    {
        return $this->belongsTo(Procurement::class);
    }

    public function plenaryMeetingItem()
    {
        return $this->belongsTo(PlenaryMeetingItem::class);
    }

    public function deliveryLogs()
    {
        return $this->hasMany(ProcurementItemStatusLog::class)->orderByDesc('id');
    }

    public function processLogs()
    {
        return $this->hasMany(ProcurementItemProcessStatus::class)->orderByDesc('id');
    }

    public function item()
    {
        return $this->hasOneThrough(
            Item::class,
            PlenaryMeetingItem::class,
            'id',
            'id',
            'plenary_meeting_item_id',
            'item_id'
        );
    }

    public function cooperative()
    {
        return $this->hasOneThrough(
            Cooperative::class,
            PlenaryMeetingItem::class,
            'id',
            'id',
            'plenary_meeting_item_id',
            'cooperative_id'
        );
    }
}
