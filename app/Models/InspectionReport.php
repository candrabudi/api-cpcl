<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InspectionReport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'procurement_id',
        'shipment_id',
        'report_number',
        'inspection_date',
        'inspector_name',
        'notes',
        'status',
        'created_by',
    ];

    public function procurement()
    {
        return $this->belongsTo(Procurement::class);
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function items()
    {
        return $this->hasMany(InspectionReportItem::class);
    }

    public function photos()
    {
        return $this->hasMany(InspectionReportPhoto::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
