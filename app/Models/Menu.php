<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $table = 'menus';

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'image_url',
        'is_available',
        'is_featured',
        'tag',
        'discount_price',
        'discount_end_time',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
        'discount_price' => 'decimal:2',
        'discount_end_time' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
