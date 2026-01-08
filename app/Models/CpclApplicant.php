<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CpclApplicant extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $fillable = [
        'cpcl_document_id',
        'full_name',
        'nik',
        'phone_number',
        'address',
    ];

    public function document()
    {
        return $this->belongsTo(CpclDocument::class, 'cpcl_document_id');
    }
}
