<?php

use App\Models\BudgetCode;
use App\Models\MasterItem;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->actingAs(User::query()->where('email', 'superadmin@admin.com')->firstOrFail());
});

it('memuat PRK PLN 1 dan PLN 2 sejak migrasi', function () {
    foreach (['IC01062023', 'IC00462022', 'IC00892023', 'IC01362022', 'IC00472022'] as $code) {
        $b = BudgetCode::query()->where('code', $code)->firstOrFail();
        expect($b->sub_bidang)->toBe('PLN 1')->and($b->cost_center)->toBe('I0326 - SUB BID SOL ARCH PLN 1');
    }
    expect(BudgetCode::query()->where('sub_bidang', 'PLN 2')->count())->toBeGreaterThanOrEqual(7);
});

it('Super Admin menambah pilihan Master Daftar dan mendapat pesan bila ganda', function () {
    $this->post('/master/daftar', ['group' => 'rab_unit', 'label' => 'Rim', 'active' => true])->assertSessionHasNoErrors();
    expect(MasterItem::query()->where('group', 'rab_unit')->where('label', 'Rim')->exists())->toBeTrue();
    $this->post('/master/daftar', ['group' => 'rab_unit', 'label' => 'RIM', 'active' => true])->assertSessionHasErrors('label');
});

it('menyediakan pilihan peran PIC dari template alur', function () {
    $this->get('/master')->assertInertia(fn (Assert $page) => $page->where('picRoles', fn ($r) => collect($r)->contains('Manager Sales') && collect($r)->contains('L1/VP Sales') && collect($r)->contains('Finance')));
});

it('menu Daftar TOR, BCR, BAK hanya memuat dokumen tersebut', function () {
    $this->get('/dokumen?periode=semua')->assertOk()->assertInertia(fn (Assert $page) => $page->where('rows', fn ($rows) => collect($rows)->every(fn ($r) => in_array($r['doc_type'], ['TOR (Term of Reference)', 'BCR (Benefit Cost Ratio)', 'BAK'], true))));
});
