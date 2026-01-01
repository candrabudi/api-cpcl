<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = [
        'name',
        'type',
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
}
