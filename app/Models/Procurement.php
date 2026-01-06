<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Procurement extends Model
{
    protected $fillable = [
        'plenary_meeting_id',
        'procurement_number',
        'procurement_date',
        'status',
        'notes',
    ];

    public function items()
    {
        return $this->hasMany(ProcurementItem::class);
    }

    public function plenaryMeeting()
    {
        return $this->belongsTo(PlenaryMeeting::class);
    }

    public function processStatuses()
    {
        return $this->hasOne(ProcurementItemProcessStatus::class, 'id', 'procurement_id');
    }

    public function ProcurementItem()
    {
        return $this->hasOne(ProcurementItem::class, 'id', 'procurement_id');
    }

    public function itemsOne()
    {
        return $this->hasMany(ProcurementItem::class)
                    ->with(['plenaryMeetingItem', 'plenaryMeetingItem.item'])
                    ->limit(1);
    }

    protected static function booted()
    {
        static::creating(function ($procurement) {
            if (Auth::check()) {
                $procurement->created_by = Auth::id();
            }
        });

        static::created(function ($procurement) {
            if (Auth::check()) {
                ProcurementLog::create([
                    'procurement_id' => $procurement->id,
                    'user_id' => Auth::id(),
                    'action' => 'created',
                    'changes' => $procurement->getAttributes(),
                ]);
            }
        });

        static::updating(function ($procurement) {
            if (Auth::check()) {
                $changes = [];
                foreach ($procurement->getDirty() as $field => $newValue) {
                    $changes[$field] = [
                        'old' => $procurement->getOriginal($field),
                        'new' => $newValue,
                    ];
                }

                if (!empty($changes)) {
                    ProcurementLog::create([
                        'procurement_id' => $procurement->id,
                        'user_id' => Auth::id(),
                        'action' => 'updated',
                        'changes' => $changes,
                    ]);
                }
            }
        });

        static::deleted(function ($procurement) {
            if (Auth::check()) {
                ProcurementLog::create([
                    'procurement_id' => $procurement->id,
                    'user_id' => Auth::id(),
                    'action' => 'deleted',
                    'changes' => $procurement->getAttributes(),
                ]);
            }
        });
    }

    public function logs()
    {
        return $this->hasMany(ProcurementLog::class, 'procurement_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
