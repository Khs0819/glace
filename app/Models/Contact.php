<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'name', 'phone', 'email', 'subject', 'message', 'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }
}
