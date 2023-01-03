<?php

namespace App\Models;

class Domain extends BaseModel
{    
    public function columnSettings()
    {
        return $this->hasMany(ColumnSetting::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function columns()
    {
        return $this->hasMany(User::class);
    }
}
