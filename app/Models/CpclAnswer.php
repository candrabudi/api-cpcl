<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CpclAnswer extends Model
{
    protected $fillable = [
        'cpcl_document_id',
        'cpcl_applicant_id',
        'group_field_row_id',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function document()
    {
        return $this->belongsTo(CpclDocument::class);
    }

    public function applicant()
    {
        return $this->belongsTo(CpclApplicant::class);
    }

    public function fieldRow()
    {
        return $this->belongsTo(GroupFieldRow::class, 'group_field_row_id');
    }
}
