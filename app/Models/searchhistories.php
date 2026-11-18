<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class searchhistories extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'keyword',
        'normalized_keyword',
        'matched_category_id',
        'results_count',
    ];

    protected $casts = [
        'results_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matchedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'matched_category_id');
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(SearchProductClick::class);
    }
}
