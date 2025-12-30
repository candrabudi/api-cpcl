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
        'total_paid',
        'delivery_status',
        'estimated_delivery_date',
        'actual_delivery_date',
        'received_at',
    ];

    protected $casts = [
        'estimated_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'received_at' => 'date',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function plenaryMeetingItem()
    {
        return $this->belongsTo(PlenaryMeetingItem::class);
    }

    public function procurement()
    {
        return $this->belongsTo(Procurement::class);
    }

    public function annualBudgetAllocation()
    {
        return $this->belongsTo(AnnualBudgetAllocation::class);
    }
}
