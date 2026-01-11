<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentStatusLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'status',
        'notes',
        'latitude',
        'longitude',
        'area_id',
        'created_by',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
