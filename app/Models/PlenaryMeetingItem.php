<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlenaryMeetingItem extends Model
{
    protected $fillable = [
        'plenary_meeting_id',
        'cooperative_id',
        'cpcl_document_id',
        'item_id',
        'package_quantity',
        'note',
        'location',
        'unit_price',
    ];

    public function meeting()
    {
        return $this->belongsTo(PlenaryMeeting::class, 'plenary_meeting_id');
    }

    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function cpclDocument()
    {
        return $this->belongsTo(CpclDocument::class, 'cpcl_document_id');
    }

    public function procurementItems()
    {
        return $this->hasMany(ProcurementItem::class);
    }

    public function procurementItem()
    {
        return $this->hasOne(ProcurementItem::class, 'plenary_meeting_item_id');
    }

    public function plenaryMeeting()
    {
        return $this->belongsTo(
            PlenaryMeeting::class,
            'plenary_meeting_id',
            'id'
        );
    }

    public function items()
    {
        return $this->hasMany(
            PlenaryMeetingItem::class,
            'plenary_meeting_id',
            'id'
        );
    }

    public function attendees()
    {
        return $this->hasMany(
            PlenaryMeetingAttendee::class,
            'plenary_meeting_id',
            'id'
        );
    }
}
