<?php

use App\Models\AuditLog;
use App\Models\BudgetCode;
use App\Models\Company;
use App\Models\CustomerContract;
use App\Models\Pic;
use App\Models\Procurement;
use App\Models\User;
use App\Support\Access;
use App\Support\OwnerAccounts;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->super = User::query()->where('email', 'superadmin@admin.com')->firstOrFail();
    $this->admin = User::query()->where('email', 'admin@admin.com')->firstOrFail();
});

function rbacProcurement(): Procurement
{
    $vendor = Company::query()->create(['name' => 'PT V RBAC', 'type' => 'Vendor', 'active' => true]);
    $cust = Company::query()->create(['name' => 'PT P RBAC', 'type' => 'Pelanggan', 'active' => true]);
    $cc = CustomerContract::query()->create(['kind' => 'Berita Acara Kesepakatan Biaya', 'number' => 'BAKB/RBAC/1', 'title' => 'Uji', 'customer_id' => $cust->id, 'date' => now()]);
    test()->actingAs(test()->super)->post('/pengadaan', [
        'draft' => false, 'method' => 'SPP Pusat', 'title' => 'RBAC', 'category' => 'Jasa', 'proc_method' => 'Pengadaan Langsung', 'priority' => 'Normal',
        'sub_bidang' => 'PLN 2', 'spp_number' => 'SPP/RBAC/001', 'spp_date' => now()->toDateString(), 'pic_pengaju_id' => Pic::query()->value('id'),
        'candidate_vendor_ids' => [$vendor->id], 'customer_contract_ids' => [$cc->id], 'budget_code_id' => BudgetCode::query()->value('id'),
        'rab_items' => [['description' => 'X', 'budget_type' => 'OPEX', 'qty' => 1, 'unit' => 'Paket', 'price' => 1000]],
        'documents' => [], 'workflow' => [['key' => 'pengaju', 'name' => 'Pengaju', 'group' => 'Pengaju', 'enabled' => true]],
    ])->assertSessionHasNoErrors();

    return Procurement::query()->firstOrFail();
}

it('membuat dua akun pemilik dengan kata sandi yang diminta', function () {
    expect($this->super->role)->toBe('super_admin')->and(Hash::check('*tr4ns4ct10n#', $this->super->password))->toBeTrue()
        ->and($this->admin->role)->toBe('admin')->and(Hash::check('admin123', $this->admin->password))->toBeTrue();
    // Menjalankan ulang tidak menimpa kata sandi yang sudah diganti.
    $this->admin->update(['password' => 'gantibaru1']);
    OwnerAccounts::ensure();
    expect(Hash::check('gantibaru1', $this->admin->fresh()->password))->toBeTrue()->and(User::query()->whereIn('email', User::OWNER_EMAILS)->count())->toBe(2);
});

it('login lokal dengan akun pemilik', function () {
    $this->post('/login', ['email' => 'superadmin@admin.com', 'password' => '*tr4ns4ct10n#'])->assertRedirect();
    $this->assertAuthenticatedAs($this->super);
});

it('membatasi menu Admin sesuai hak akses', function () {
    $u = User::query()->create(['name' => 'ADMIN TERBATAS', 'email' => 'terbatas@iconpln.co.id', 'password' => 'rahasia123', 'role' => 'admin',
        'permissions' => [...Access::VIEW_ONLY, 'rkap' => 'none'], 'active' => true]);
    $this->actingAs($u);
    $this->get('/pengadaan')->assertOk();
    $this->get('/pengadaan/baru')->assertForbidden();
    $this->get('/rkap')->assertForbidden();
    $this->get('/master')->assertForbidden();
    $this->get('/pengguna')->assertForbidden();
    $this->get('/pengadaan')->assertInertia(fn (Assert $page) => $page->where('auth.access.rkap', 'none')->where('auth.access.pengadaan', 'view')->where('auth.can.super', false));
    // Tanpa Dashboard: diarahkan ke menu pertama yang diizinkan.
    $u->update(['permissions' => [...Access::VIEW_ONLY, 'dashboard' => 'none']]);
    $this->get('/')->assertRedirect('/pengadaan');
});

it('hanya Super Admin yang mengelola pengguna dan hak aksesnya', function () {
    $this->actingAs($this->admin)->get('/pengguna')->assertForbidden();
    $this->actingAs($this->super)->get('/pengguna')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Users/Index')->has('menus', count(Access::MENUS)));
    $this->post('/pengguna', ['name' => 'Rina', 'email' => 'Rina@IconPLN.co.id', 'password' => 'rahasia123', 'role' => 'admin', 'active' => true,
        'permissions' => ['pengadaan' => 'edit', 'dashboard' => 'view', 'dokumen' => 'edit']])->assertSessionHasNoErrors();
    $rina = User::query()->where('email', 'rina@iconpln.co.id')->firstOrFail();
    expect($rina->access('pengadaan'))->toBe('edit')->and($rina->access('dokumen'))->toBe('view') // Dokumen maksimal Lihat
        ->and($rina->access('master'))->toBe('none');
    $this->put("/pengguna/{$rina->id}", ['name' => 'Rina', 'email' => 'rina@iconpln.co.id', 'role' => 'admin', 'active' => false, 'permissions' => $rina->permissions])->assertSessionHasNoErrors();
    expect($rina->fresh()->active)->toBeFalse()->and(AuditLog::query()->where('action', 'Nonaktifkan akun')->exists())->toBeTrue();
    // Akun pemilik tidak dapat dinonaktifkan atau diturunkan.
    $this->put("/pengguna/{$this->super->id}", ['name' => 'X', 'email' => 'superadmin@admin.com', 'role' => 'admin', 'active' => true])->assertSessionHasErrors('role');
    $this->put("/pengguna/{$this->admin->id}", ['name' => 'X', 'email' => 'admin@admin.com', 'role' => 'admin', 'active' => false])->assertSessionHasErrors('role');
});

it('hanya Super Admin yang dapat menghapus pengadaan, dengan konfirmasi', function () {
    $p = rbacProcurement();
    $this->actingAs($this->admin)->delete("/pengadaan/{$p->code}", ['reason' => 'Data ganda', 'confirm' => 'SPP/RBAC/001'])->assertForbidden();
    $this->actingAs($this->super)->delete("/pengadaan/{$p->code}", ['reason' => 'Data ganda', 'confirm' => 'SALAH'])->assertSessionHasErrors('confirm');
    $this->delete("/pengadaan/{$p->code}", ['reason' => 'Data ganda', 'confirm' => 'spp/rbac/001'])->assertRedirect('/pengadaan');
    expect(Procurement::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'Hapus pengadaan')->value('reason'))->toBe('Data ganda');
    $this->get("/pengadaan/{$p->code}")->assertNotFound();
});
