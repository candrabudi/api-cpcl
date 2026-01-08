<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Vendor extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'area_id',
        'user_id',
        'name',
        'npwp',
        'contact_person',
        'phone',
        'email',
        'address',
        'latitude',
        'longitude',
        'total_paid',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function procurements()
    {
        return $this->hasMany(Procurement::class);
    }


    public function logs()
    {
        return $this->hasMany(VendorLog::class);
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
}
