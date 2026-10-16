<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Dua akun pemilik awal Procfolio. Dipastikan ada oleh migrasi RBAC dan ReferenceSeeder,
 * sehingga ikut terbawa setiap kali database dimigrasi atau diisi ulang.
 */
final class OwnerAccounts
{
    /** @return array<int, array{email:string, name:string, role:string, password:string}> */
    public static function all(): array
    {
        return [
            ['email' => 'superadmin@admin.com', 'name' => 'Super Admin Procfolio', 'role' => 'super_admin', 'password' => (string) config('procfolio.owner_passwords.super_admin')],
            ['email' => 'admin@admin.com', 'name' => 'Admin Procfolio', 'role' => 'admin', 'password' => (string) config('procfolio.owner_passwords.admin')],
        ];
    }

    /** Buat akun bila belum ada; peran dan status aktif dipulihkan. Kata sandi yang sudah diganti tidak ditimpa. */
    public static function ensure(): void
    {
        foreach (self::all() as $o) {
            $row = DB::table('users')->where('email', $o['email'])->first();
            if ($row) {
                DB::table('users')->where('id', $row->id)->update(['role' => $o['role'], 'active' => true]);

                continue;
            }
            DB::table('users')->insert([
                'name' => $o['name'], 'email' => $o['email'], 'password' => Hash::make($o['password']), 'role' => $o['role'],
                'permissions' => json_encode(Access::full()), 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
