<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'full_name','nik','photo','email','username',
        'password','plain_password','role','division_id'
    ];

    protected $hidden = ['password','remember_token'];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }
}