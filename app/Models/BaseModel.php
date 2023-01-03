<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    protected $guarded = [];  // Разрешаем изменение всех полей каждой модели (ничего не "охраняем")
    public $timestamps = false; // Убираем столбцы "Время изменения" и "Время создания" из представления Laravel о таблицах
}
