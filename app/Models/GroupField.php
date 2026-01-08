<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupField extends Model
{
    use SoftDeletes;
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
