<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CpclFishingVessel extends Model
{
    protected $table = 'cpcl_fishing_vessels';

    protected $fillable = [
        'cpcl_document_id',
        'ship_type',
        'engine_brand',
        'engine_power',
        'quantity',
    ];

    public function cpclDocument()
    {
        return $this->belongsTo(CpclDocument::class);
    }
}
