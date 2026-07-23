<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory;

    protected $fillable = ['question', 'answer', 'keywords', 'category', 'priority', 'is_active'];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return array<int, string>
     */
    public function keywordList(): array
    {
        return collect(explode(',', (string) $this->keywords))
            ->map(fn ($k) => trim(mb_strtolower($k)))
            ->filter()
            ->values()
            ->all();
    }
}
