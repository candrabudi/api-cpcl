<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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

    protected static function booted()
    {
        static::created(function ($vendor) {
            if (Auth::check()) {
                VendorLog::create([
                    'vendor_id' => $vendor->id,
                    'user_id' => Auth::id(),
                    'action' => 'created',
                    'changes' => $vendor->getAttributes(),
                ]);
            }
        });

        static::updating(function ($vendor) {
            if (Auth::check()) {
                $changes = [];
                foreach ($vendor->getDirty() as $field => $newValue) {
                    $changes[$field] = [
                        'old' => $vendor->getOriginal($field),
                        'new' => $newValue,
                    ];
                }

                if (!empty($changes)) {
                    VendorLog::create([
                        'vendor_id' => $vendor->id,
                        'user_id' => Auth::id(),
                        'action' => 'updated',
                        'changes' => $changes,
                    ]);
                }
            }
        });

        static::deleted(function ($vendor) {
            if (Auth::check()) {
                VendorLog::create([
                    'vendor_id' => $vendor->id,
                    'user_id' => Auth::id(),
                    'action' => 'deleted',
                    'changes' => $vendor->getAttributes(),
                ]);
            }
        });
    }

    public function logs()
    {
        return $this->hasMany(VendorLog::class);
    }
}
