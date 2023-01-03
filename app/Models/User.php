<?php

namespace App\Models;

class User extends BaseModel
{    
    public function columnSettings()
    {
        return $this->hasMany(ColumnSetting::class);
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }
}
