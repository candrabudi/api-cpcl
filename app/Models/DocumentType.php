<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    protected $fillable = ['name', 'description'];

    public function documents()
    {
        return $this->hasMany(VendorDocument::class, 'document_type_id');
    }
}
