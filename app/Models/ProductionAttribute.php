<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionAttribute extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'item_type_id',
        'name',
        'slug',
        'description',
        'sort_order',
        'default_percentage',
        'is_active',
    ];

    public function itemType()
    {
        return $this->belongsTo(ItemType::class);
    }

    public function processStatuses()
    {
        return $this->hasMany(ProcurementItemProcessStatus::class);
    }
}
