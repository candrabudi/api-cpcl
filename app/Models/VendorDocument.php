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

    protected $appends = ['file_url'];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function type()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    // Accessor untuk generate URL di Lumen
    public function getFileUrlAttribute()
    {
        if (!$this->file_path) {
            return null;
        }

        // Ambil host + scheme dari request
        $schemeAndHost = request()->getSchemeAndHttpHost();

        // Kembalikan URL lengkap
        return $schemeAndHost.'/storage/'.$this->file_path;
    }
}
