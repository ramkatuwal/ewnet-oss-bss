<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'registration_number',
        'pan_number',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'website',
        'settings',
        'is_active'
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean'
    ];

    public function regions()
    {
        return $this->hasMany(Region::class);
    }

    public function branches()
    {
        return $this->hasManyThrough(Branch::class, Region::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
