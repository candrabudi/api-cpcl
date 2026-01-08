<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementItem extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'procurement_id',
        'plenary_meeting_item_id',
        'plenary_meeting_id',
        'quantity',
        'unit_price',
        'total_price',
        'delivery_status',
        'process_status',
        'created_by',
    ];

    public function procurement()
    {
        return $this->belongsTo(Procurement::class);
    }

    public function plenaryMeeting()
    {
        return $this->belongsTo(PlenaryMeeting::class);
    }

    public function plenaryMeetingItem()
    {
        return $this->belongsTo(PlenaryMeetingItem::class);
    }

    public function processStatuses()
    {
        return $this->hasMany(ProcurementItemProcessStatus::class, 'procurement_item_id');
    }

    public function statusLogs()
    {
        return $this->hasMany(ShipmentStatusLog::class, 'procurement_item_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function item()
    {
        return $this->hasOneThrough(
            Item::class,
            PlenaryMeetingItem::class,
            'id',
            'id',
            'plenary_meeting_item_id',
            'item_id'
        );
    }

    public function cooperative()
    {
        return $this->hasOneThrough(
            Cooperative::class,
            PlenaryMeetingItem::class,
            'id',
            'id',
            'plenary_meeting_item_id',
            'cooperative_id'
        );
    }
}
