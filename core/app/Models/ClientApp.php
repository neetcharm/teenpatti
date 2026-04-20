<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientApp extends Model
{
    protected $fillable = ['name', 'client_key', 'client_secret', 'allowed_origins', 'status'];

    protected $hidden = ['client_secret'];

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
