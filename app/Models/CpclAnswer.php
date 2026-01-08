<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CpclAnswer extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'cpcl_document_id',
        'group_field_row_id',
        'answer_value',
    ];

    protected $casts = [
        'answer_value' => 'array',
    ];

    public function document()
    {
        return $this->belongsTo(CpclDocument::class, 'cpcl_document_id');
    }

    public function fieldRow()
    {
        return $this->belongsTo(GroupFieldRow::class, 'group_field_row_id');
    }
}
