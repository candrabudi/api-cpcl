<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupField extends Model
{
    protected $fillable = [
        'title',
        'prepared_by',
        'notes',
    ];

    public function rows()
    {
        return $this->hasMany(GroupFieldRow::class, 'group_field_id')
            ->whereNull('parent_id')
            ->orderBy('order_no');
    }

    public function allRows()
    {
        return $this->hasMany(GroupFieldRow::class, 'group_field_id')
            ->orderBy('order_no');
    }
}
