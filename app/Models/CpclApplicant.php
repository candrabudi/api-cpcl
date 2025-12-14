<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CpclApplicant extends Model
{
    use HasFactory;

    protected $fillable = [
        'cpcl_document_id',
        'area_id',
        'cooperative_id',
        'group_name',
        'cooperative_registration_number',
        'kusuka_id_number',
        'established_year',
        'street_address',
        'village',
        'district',
        'regency',
        'province',
        'village',
        'district',
        'regency',
        'province',
        'latitude',
        'longitude',
        'phone_number',
        'email',
        'member_count',
        'chairman_name',
        'secretary_name',
        'treasurer_name',
        'chairman_phone_number',
    ];

    protected $casts = [
        'established_year' => 'integer',
        'member_count' => 'integer',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }
}
