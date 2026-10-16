<?php

use App\Support\Access;
use App\Support\OwnerAccounts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC: peran Super Admin dan Admin, serta hak akses per menu (kolom permissions).
 * Peran lama dipetakan: admin -> Admin dengan seluruh menu Ubah; pengaju -> Admin bawaan; viewer -> Admin hanya lihat.
 * Dua akun pemilik awal selalu dipastikan ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->json('permissions')->nullable()->after('role');
        });
        foreach (DB::table('users')->get(['id', 'role']) as $u) {
            $perm = match ($u->role) {
                'admin' => Access::full(),
                'viewer' => Access::VIEW_ONLY,
                default => Access::ADMIN_DEFAULT,
            };
            DB::table('users')->where('id', $u->id)->update(['role' => 'admin', 'permissions' => json_encode($perm)]);
        }
        OwnerAccounts::ensure();
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'super_admin')->update(['role' => 'admin']);
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('permissions'));
    }
};
