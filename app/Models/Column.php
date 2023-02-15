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

    public static function boot() {
        parent::boot();

        static::deleting(function($user) {
            $user->settings()->delete();
        });
    }

    protected $attributes = [
        'groupable' => true,
        'sortable' => true,
        'filterable' => true,
    ];
}
