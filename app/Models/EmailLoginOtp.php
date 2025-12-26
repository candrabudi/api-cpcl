<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLoginOtp extends Model
{
    protected $table = 'email_login_otps';

    protected $fillable = [
        'email',
        'otp',
        'expired_at',
        'verified_at',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'verified_at' => 'datetime',
    ];
}
