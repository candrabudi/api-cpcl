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

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function procurementItem()
    {
        return $this->belongsTo(ProcurementItem::class);
    }
}
