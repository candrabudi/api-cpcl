<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $fillable = [
        'area_id',
        'user_id',
        'name',
        'npwp',
        'contact_person',
        'phone',
        'email',
        'address',
        'total_paid',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function area()
    {
        return $this->hasOne(Area::class, 'id', 'area_id');
    }

    public function procurementItems()
    {
        return $this->hasMany(ProcurementItem::class);
    }

    public function documents()
    {
        return $this->hasMany(VendorDocument::class);
    }
}
