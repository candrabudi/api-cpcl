<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Item extends Model
{
    protected $fillable = [
        'name',
        'type',
        'item_type_id',
        'code',
        'brand',
        'model',
        'specification',
        'unit',
        'weight',
        'length',
        'width',
        'height',
        'description',
    ];

    public function plenaryMeetingItems()
    {
        return $this->hasMany(PlenaryMeetingItem::class);
    }

    public function procurementItems()
    {
        return $this->hasManyThrough(
            ProcurementItem::class,
            PlenaryMeetingItem::class,
            'item_id',
            'plenary_meeting_item_id',
            'id',
            'id'
        );
    }

    protected static function booted()
    {
        static::creating(function ($item) {
            if (Auth::check()) {
                $item->created_by = Auth::id();
            }
        });

        static::created(function ($item) {
            if (Auth::check()) {
                ItemLog::create([
                    'item_id' => $item->id,
                    'user_id' => Auth::id(),
                    'action' => 'created',
                    'changes' => $item->getAttributes(),
                ]);
            }
        });

        static::updating(function ($item) {
            if (Auth::check()) {
                $changes = [];
                foreach ($item->getDirty() as $field => $newValue) {
                    $changes[$field] = [
                        'old' => $item->getOriginal($field),
                        'new' => $newValue,
                    ];
                }

                if (!empty($changes)) {
                    ItemLog::create([
                        'item_id' => $item->id,
                        'user_id' => Auth::id(),
                        'action' => 'updated',
                        'changes' => $changes,
                    ]);
                }
            }
        });

        static::deleted(function ($item) {
            if (Auth::check()) {
                ItemLog::create([
                    'item_id' => $item->id,
                    'user_id' => Auth::id(),
                    'action' => 'deleted',
                    'changes' => $item->getAttributes(),
                ]);
            }
        });
    }

    public function logs()
    {
        return $this->hasMany(ItemLog::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function type()
    {
        return $this->belongsTo(ItemType::class, 'item_type_id');
    }
}
