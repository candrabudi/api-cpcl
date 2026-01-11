<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionReportPhoto extends Model
{
    protected $fillable = [
        'inspection_report_id',
        'photo_path',
        'caption',
    ];

    public function report()
    {
        return $this->belongsTo(InspectionReport::class);
    }
}
