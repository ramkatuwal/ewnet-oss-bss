<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetCategory extends Model
{
    protected $fillable = ['code', 'name', 'description', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function deviceTypes(): HasMany
    {
        return $this->hasMany(AssetDeviceType::class, 'category_id');
    }

    public function activeDeviceTypes(): HasMany
    {
        return $this->deviceTypes()->where('is_active', true)->orderBy('sort_order');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'category', 'code');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
