<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShipmentItem extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'procurement_item_id',
        'quantity',
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

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function procurementItem()
    {
        return $this->belongsTo(ProcurementItem::class);
    }
}
