<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'cooperative_id',
        'tracking_number',
        'status',
        'shipped_at',
        'delivered_at',
        'received_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function statusLogs()
    {
        return $this->hasMany(ShipmentStatusLog::class)->orderByDesc('id');
    }

    public function inspectionReports()
    {
        return $this->hasMany(InspectionReport::class);
    }
}
