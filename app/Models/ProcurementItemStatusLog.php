<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcurementItemStatusLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'procurement_item_id',
        'old_status',
        'new_status',
        'changed_by',
        'notes',
        'received_at',
    ];

    protected $casts = [
        'received_at' => 'date',
    ];

    public function procurementItem()
    {
        return $this->belongsTo(ProcurementItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
