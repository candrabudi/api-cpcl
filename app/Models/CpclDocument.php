<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CpclDocument extends Model
{
    protected $fillable = [
        'cpcl_number',
        'title',
        'program_code',
        'year',
        'cpcl_date',
        'cpcl_month',
        'status',
        'pleno_result',
        'version',
        'submitted_date',
        'pleno_date',
        'pleno_notes',
        'prepared_by',
        'verified_by',
        'approved_by',
        'verified_at',
        'approved_at',
    ];

    public function fishingVessels()
    {
        return $this->hasMany(CpclFishingVessel::class);
    }
}
