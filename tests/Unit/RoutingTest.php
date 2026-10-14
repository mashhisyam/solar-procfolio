<?php

use App\Services\WorkflowService;

function flow(): array
{
    $mk = fn (string $key, bool $on = true) => ['key' => $key, 'name' => $key, 'enabled' => $on, 'optional' => false];

    return [$mk('anggaran_staff'), $mk('pengadaan_mgr'), $mk('rendan_spv'), $mk('rendan_staff'), $mk('lakdan_spv'), $mk('lakdan_staff')];
}

it('Pengadaan Langsung melewati Manager Pengadaan lalu langsung ke Lakdan', function () {
    $keys = collect(WorkflowService::applyRouting(flow(), 'Pengadaan Langsung'))->where('enabled', true)->pluck('key')->all();

    expect($keys)->toBe(['anggaran_staff', 'pengadaan_mgr', 'lakdan_spv', 'lakdan_staff']);
});

it('metode selain Pengadaan Langsung melewati Manager Pengadaan, Rendan, lalu Lakdan', function () {
    $keys = collect(WorkflowService::applyRouting(flow(), 'Tender Terbuka'))->where('enabled', true)->pluck('key')->all();

    expect($keys)->toBe(['anggaran_staff', 'pengadaan_mgr', 'rendan_spv', 'rendan_staff', 'lakdan_spv', 'lakdan_staff']);
});

it('Manager Pengadaan selalu aktif walaupun dinonaktifkan pengguna', function () {
    $off = array_map(fn ($s) => $s['key'] === 'pengadaan_mgr' ? [...$s, 'enabled' => false, 'optional' => true] : $s, flow());
    $mgr = collect(WorkflowService::enforceRequired($off))->firstWhere('key', 'pengadaan_mgr');

    expect($mgr['enabled'])->toBeTrue()->and($mgr['optional'])->toBeFalse();
});
