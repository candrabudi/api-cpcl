<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cooperative extends Model
{
    use SoftDeletes;
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
        'member_count' => 'integer',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function cpclApplicants()
    {
        // This might be outdated since CpclApplicant belongs to CpclDocument
        return $this->hasMany(CpclApplicant::class);
    }

    public function plenaryMeetingItems()
    {
        return $this->hasMany(PlenaryMeetingItem::class);
    }

    public function logs()
    {
        return $this->hasMany(CooperativeLog::class);
    }
}
