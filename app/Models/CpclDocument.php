<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CpclDocument extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'title',
        'program_code',
        'year',
        'cpcl_date',
        'cpcl_month',
        'prepared_by',
        'status',
        'notes',
        'created_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function applicants()
    {
        return $this->hasMany(CpclApplicant::class);
    }

    public function answers()
    {
        return $this->hasMany(CpclAnswer::class);
    }

    public function fishingVessels()
    {
        return $this->hasMany(CpclFishingVessel::class);
    }

    public function plenaryMeetingItems()
    {
        return $this->hasMany(PlenaryMeetingItem::class);
    }
}
