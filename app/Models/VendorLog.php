<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorLog extends Model
{
    use HasFactory;

    protected $fillable = ['vendor_id', 'user_id', 'action', 'changes'];

    protected $casts = [
        'changes' => 'array',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
