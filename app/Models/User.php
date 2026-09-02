<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, LogsActivity;

    const ROLE_OWNER_ADMIN = 'owner_admin';
    const ROLE_BRANCH_MANAGER = 'branch_manager';
    const ROLE_PHARMACIST_CLERK = 'pharmacist_clerk';

    const ROLES = [
        self::ROLE_OWNER_ADMIN => 'Owner / Admin',
        self::ROLE_BRANCH_MANAGER => 'Branch Manager',
        self::ROLE_PHARMACIST_CLERK => 'Pharmacist / Clerk',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'branch_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ucfirst(str_replace('_', ' ', $this->role ?? ''));
    }
}
