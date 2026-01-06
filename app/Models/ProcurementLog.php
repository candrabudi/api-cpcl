<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcurementLog extends Model
{
    use HasFactory;

    protected $fillable = ['procurement_id', 'user_id', 'action', 'changes'];

    protected $casts = [
        'changes' => 'array',
    ];

    public function procurement()
    {
        return $this->belongsTo(Procurement::class, 'procurement_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
