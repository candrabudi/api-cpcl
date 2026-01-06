<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlenaryMeetingLog extends Model
{
    use HasFactory;

    protected $fillable = ['plenary_meeting_id', 'user_id', 'action', 'changes'];

    protected $casts = [
        'changes' => 'array',
    ];

    public function meeting()
    {
        return $this->belongsTo(PlenaryMeeting::class, 'plenary_meeting_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
