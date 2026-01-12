<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CpclFishingVessel extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'cpcl_document_id',
        'ship_type',
        'engine_brand',
        'engine_power',
        'quantity',
    ];

    public function document()
    {
        return $this->belongsTo(CpclDocument::class, 'cpcl_document_id');
    }
}
