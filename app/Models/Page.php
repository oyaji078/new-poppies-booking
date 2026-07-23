<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'title', 'body', 'meta', 'is_published'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
