<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupFieldRow extends Model
{
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
    ];

    public function document()
    {
        return $this->belongsTo(CpclDocument::class, 'group_field_id');
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

    public function allChildren()
    {
        return $this->children()->with('allChildren');
    }

    public function childrenCount()
    {
        return $this->children()->count();
    }
}
