<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cooperative extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'area_id',
        'registration_number',
        'kusuka_id_number',
        'established_year',
        'street_address',
        'village',
        'district',
        'regency',
        'province',
        'phone_number',
        'email',
        'chairman_name',
        'secretary_name',
        'treasurer_name',
        'chairman_phone_number',
        'member_count',
    ];

    protected $casts = [
        'established_year' => 'integer',
    ];

    public function cpclApplicants()
    {
        return $this->hasMany(CpclApplicant::class);
    }
}
