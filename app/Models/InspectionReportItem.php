<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionReportItem extends Model
{
    protected $fillable = [
        'inspection_report_id',
        'procurement_item_id',
        'shipment_item_id',
        'expected_quantity',
        'actual_quantity',
        'is_matched',
        'condition',
        'notes',
    ];

    public function report()
    {
        return $this->belongsTo(InspectionReport::class, 'inspection_report_id');
    }

    public function procurementItem()
    {
        return $this->belongsTo(ProcurementItem::class);
    }

    public function shipmentItem()
    {
        return $this->belongsTo(ShipmentItem::class);
    }
}
