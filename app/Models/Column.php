<?php

namespace App\Models;

class Column extends BaseModel
{
    public function settings()
    {
        return $this->hasMany(ColumnSetting::class);
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    protected $attributes = [
        'groupable' => true,
        'sortable' => true,
        'filterable' => true,
    ];
}
