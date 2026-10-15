<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLES = ['admin', 'pengaju', 'viewer'];

    protected $fillable = ['name', 'email', 'password', 'microsoft_id', 'role', 'pic_id', 'active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'active' => 'boolean'];
    }

    /** @return BelongsTo<Pic, $this> */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(Pic::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function canEdit(): bool
    {
        return in_array($this->role, ['admin', 'pengaju'], true);
    }
}
