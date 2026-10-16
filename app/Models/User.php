<?php

namespace App\Models;

use App\Support\Access;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property string $role super_admin | admin
 * @property array<string, string>|null $permissions hak akses per menu (none | view | edit)
 * @property bool $active
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const SUPER_ADMIN = 'super_admin';

    public const ADMIN = 'admin';

    public const ROLES = [self::SUPER_ADMIN, self::ADMIN];

    public const ROLE_LABELS = [self::SUPER_ADMIN => 'Super Admin', self::ADMIN => 'Admin'];

    /** Akun pemilik awal; selalu dibuat oleh migrasi dan ReferenceSeeder, tidak dapat dinonaktifkan. */
    public const OWNER_EMAILS = ['superadmin@admin.com', 'admin@admin.com'];

    protected $fillable = ['name', 'email', 'password', 'microsoft_id', 'role', 'permissions', 'pic_id', 'active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'active' => 'boolean', 'permissions' => 'array'];
    }

    /** @return BelongsTo<Pic, $this> */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(Pic::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->active && $this->role === self::SUPER_ADMIN;
    }

    /** Tingkat akses pengguna pada satu menu: none | view | edit. Super Admin selalu tingkat tertinggi. */
    public function access(string $menu): string
    {
        if (! $this->active || ! isset(Access::MENUS[$menu])) {
            return Access::NONE;
        }
        if ($this->role === self::SUPER_ADMIN) {
            return Access::top($menu);
        }

        return Access::normalize($this->permissions ?? [])[$menu];
    }

    public function allows(string $menu, string $level = Access::VIEW): bool
    {
        return Access::rank($this->access($menu)) >= Access::rank($level);
    }

    /** @return array<string, string> */
    public function accessMap(): array
    {
        return collect(Access::MENUS)->keys()->mapWithKeys(fn ($k) => [$k => $this->access($k)])->all();
    }

    /** Halaman pertama yang boleh dibuka (dipakai bila Dashboard tidak diizinkan). */
    public function homeUrl(): ?string
    {
        foreach (Access::MENUS as $key => [, $url]) {
            if ($this->allows($key)) {
                return $url;
            }
        }

        return null;
    }

    // Kompatibilitas lama: dipakai gate 'edit' dan 'admin'.
    public function isAdmin(): bool
    {
        return $this->allows('master', Access::EDIT);
    }

    public function canEdit(): bool
    {
        return $this->allows('pengadaan', Access::EDIT);
    }
}
