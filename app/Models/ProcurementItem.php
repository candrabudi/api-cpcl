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
    ];

    public function procurement()
    {
        return $this->belongsTo(Procurement::class);
    }

    public function plenaryItem()
    {
        return $this->belongsTo(PlenaryMeetingItem::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
