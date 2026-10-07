<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'sponsor_id',
        'sponsor_user_id',
        'name',
        'email',
        'phone',
        'password',
        'security_pin',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'security_pin',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * The user who sponsored this member.
     */
    public function sponsor()
    {
        return $this->belongsTo(
            User::class,
            'sponsor_user_id'
        );
    }

    /**
     * Members directly sponsored by this user.
     */
    public function referrals()
    {
        return $this->hasMany(
            User::class,
            'sponsor_user_id'
        );
    }
}