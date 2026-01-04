<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorDocument extends Model
{
    protected $fillable = [
        'vendor_id',
        'document_type_id',
        'file_path',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function type()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }
}
