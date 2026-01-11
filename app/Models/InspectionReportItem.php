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

    protected $appends = [
        'item_name',
        'item_type_name',
        'process_type'
    ];

    public function getItemNameAttribute()
    {
        return $this->procurementItem?->item_name;
    }

    public function getItemTypeNameAttribute()
    {
        return $this->procurementItem?->item_type_name;
    }

    public function getProcessTypeAttribute()
    {
        return $this->procurementItem?->process_type;
    }

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
