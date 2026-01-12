<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HandoverCertificate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'procurement_id',
        'inspection_report_id',
        'cooperative_id',
        'report_number',
        'handover_date',
        'first_party_name',
        'first_party_nip',
        'first_party_position',
        'first_party_address',
        'second_party_name',
        'second_party_position',
        'second_party_address',
        'location_description',
        'latitude',
        'longitude',
        'status',
        'notes',
        'created_by',
    ];

    public function inspectionReport()
    {
        return $this->belongsTo(InspectionReport::class);
    }

    public function procurement()
    {
        return $this->belongsTo(Procurement::class);
    }

    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    public function items()
    {
        return $this->hasMany(HandoverCertificateItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
