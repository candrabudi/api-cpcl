<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlenaryMeetingItemLog extends Model
{
    use HasFactory;

    protected $fillable = ['plenary_meeting_item_id', 'user_id', 'action', 'changes'];

    protected $casts = [
        'changes' => 'array',
    ];

    public function item()
    {
        return $this->belongsTo(PlenaryMeetingItem::class, 'plenary_meeting_item_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
