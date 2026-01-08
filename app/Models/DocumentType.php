<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentType extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'description'];

    public function documents()
    {
        return $this->hasMany(VendorDocument::class, 'document_type_id');
    }
}
