<?php

namespace App\Models;

class ColumnSetting extends BaseModel
{
    public function column()
    {
        return $this->belongsTo(Column::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    protected $attributes = [
        'displayed' => false,
        'grouped' => false,
        'sort_desc' => false,
        'sort_order' => -1,
    ];
}
