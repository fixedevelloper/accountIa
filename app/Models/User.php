<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes,HasApiTokens;

    protected $fillable = [
        'name', 'phone', 'email', 'password', 'role_id'
    ];

    protected $hidden = ['password', 'remember_token'];

    // Relations
    public function role() {
        return $this->belongsTo(Role::class);
    }

    public function companyUser() {
        return $this->hasOne(CompanyUser::class);
    }

    public function auditLogs() {
        return $this->hasMany(AuditLog::class);
    }
}
