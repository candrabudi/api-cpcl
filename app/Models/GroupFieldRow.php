<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupFieldRow extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'group_field_id',
        'label',
        'value',
        'row_type',
        'parent_id',
        'order_no',
        'meta',
        'is_required',
    ];

    protected $casts = [
        'value' => 'array',
        'meta' => 'array',
        'is_required' => 'boolean',
    ];

    public function groupField()
    {
        return $this->belongsTo(GroupField::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('order_no');
    }

    public function answers()
    {
        return $this->hasMany(CpclAnswer::class, 'group_field_row_id');
    }
}
