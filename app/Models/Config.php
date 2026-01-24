<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    protected $fillable = ['api_key', 'base_url', 'foundry_url', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
