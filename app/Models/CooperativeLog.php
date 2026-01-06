<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CooperativeLog extends Model
{
    use HasFactory;

    protected $fillable = ['cooperative_id', 'user_id', 'action', 'changes'];

    protected $casts = [
        'changes' => 'array',
    ];

    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted()
    {
        static::created(function ($cooperative) {
            if (Auth::check()) {
                CooperativeLog::create([
                    'cooperative_id' => $cooperative->id,
                    'user_id' => Auth::id(),
                    'action' => 'created',
                    'changes' => $cooperative->getAttributes(),
                ]);
            }
        });

        static::updating(function ($cooperative) {
            if (Auth::check()) {
                $changes = [];
                foreach ($cooperative->getDirty() as $field => $newValue) {
                    $changes[$field] = [
                        'old' => $cooperative->getOriginal($field),
                        'new' => $newValue,
                    ];
                }

                if (!empty($changes)) {
                    CooperativeLog::create([
                        'cooperative_id' => $cooperative->id,
                        'user_id' => Auth::id(),
                        'action' => 'updated',
                        'changes' => $changes,
                    ]);
                }
            }
        });

        static::deleted(function ($cooperative) {
            if (Auth::check()) {
                CooperativeLog::create([
                    'cooperative_id' => $cooperative->id,
                    'user_id' => Auth::id(),
                    'action' => 'deleted',
                    'changes' => $cooperative->getAttributes(),
                ]);
            }
        });
    }

    public function logs()
    {
        return $this->hasMany(CooperativeLog::class);
    }
}
