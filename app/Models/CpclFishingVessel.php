<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CpclFishingVessel extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'cpcl_document_id',
        'vessel_name',
        'owner_name',
        'gt_volume',
        'vessel_type',
    ];

    public function document()
    {
        return $this->belongsTo(CpclDocument::class, 'cpcl_document_id');
    }
}
