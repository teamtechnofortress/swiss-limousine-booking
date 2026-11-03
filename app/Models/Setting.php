<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Setting extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'app_settings';

    protected $fillable = ['key', 'value', 'type', 'group', 'description', 'is_secret', 'editable_by', 'updated_by', 'parent_key', 'is_master'];

    protected function casts(): array
    {
        return ['is_secret' => 'boolean'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('site_logo')->singleFile()->acceptsMimeTypes([
            'image/jpeg', 'image/png', 'image/webp',
        ]);
    }
}
