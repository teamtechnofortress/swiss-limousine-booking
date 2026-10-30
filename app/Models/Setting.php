<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = ['key', 'value', 'type', 'group', 'description', 'is_secret', 'editable_by', 'updated_by'];

    protected function casts(): array
    {
        return ['is_secret' => 'boolean'];
    }
}
