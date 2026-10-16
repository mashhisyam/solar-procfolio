<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AmendmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MicrosoftController;
use App\Http\Controllers\BeritaAcaraController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\CustomerContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\Master\MasterController;
use App\Http\Controllers\PaymentTermController;
use App\Http\Controllers\PksController;
use App\Http\Controllers\ProcessController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\RkapController;
use App\Http\Controllers\SettlementController;
use Illuminate\Support\Facades\Route;

// ---- Autentikasi ----
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/auth/microsoft', [MicrosoftController::class, 'redirect']);
    Route::get('/auth/microsoft/callback', [MicrosoftController::class, 'callback']);
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth');

Route::middleware('auth')->group(function () {
    // Hak akses per menu: menu:{kunci},{view|edit}. Lihat App\Support\Access.
    Route::get('/', DashboardController::class)->middleware('menu:dashboard');

    // ---- Daftar Pengadaan (Ubah = semua aksi di halaman detail pengadaan) ----
    Route::middleware('menu:pengadaan')->group(function () {
        Route::get('/pengadaan', [ProcurementController::class, 'index']);
        Route::get('/pengadaan/baru', [ProcurementController::class, 'create'])->middleware('menu:pengadaan,edit');
        Route::get('/pengadaan/{procurement}', [ProcurementController::class, 'show']);
        Route::get('/pengadaan/{procurement}/edit', [ProcurementController::class, 'edit'])->middleware('menu:pengadaan,edit');
        Route::get('/ekspor/pengadaan', [ExportController::class, 'procurements']);
    });
    Route::middleware('menu:pengadaan,edit')->group(function () {
        Route::post('/pengadaan', [ProcurementController::class, 'store']);
        Route::put('/pengadaan/{procurement}', [ProcurementController::class, 'update']);
        Route::post('/pengadaan/{procurement}/batal', [ProcurementController::class, 'cancel']);
        Route::post('/pengadaan/{procurement}/aktifkan', [ProcurementController::class, 'reactivate']);

        Route::post('/pengadaan/{procurement}/proses', [ProcessController::class, 'store']);
        Route::post('/pengadaan/{procurement}/proses/buka', [ProcessController::class, 'reopen']);

        Route::post('/pengadaan/{procurement}/kontrak', [ContractController::class, 'store']);
        Route::post('/pengadaan/{procurement}/kontrak-pengadaan', [ContractController::class, 'storeWork']);
        Route::post('/pengadaan/{procurement}/amandemen', [AmendmentController::class, 'store']);
        Route::post('/pengadaan/{procurement}/amandemen/{amendment}/rilis', [AmendmentController::class, 'release']);

        Route::post('/pengadaan/{procurement}/tahapan', [PaymentTermController::class, 'store']);
        Route::put('/pengadaan/{procurement}/tahapan/{term}', [PaymentTermController::class, 'update']);
        Route::delete('/pengadaan/{procurement}/tahapan/{term}', [PaymentTermController::class, 'destroy']);
        Route::post('/pengadaan/{procurement}/tahapan/{term}/gr', [SettlementController::class, 'saveGr']);
        Route::post('/pengadaan/{procurement}/tahapan/{term}/invoice', [SettlementController::class, 'saveInvoice']);
        Route::post('/pengadaan/{procurement}/invoice/{invoice}/bayar', [SettlementController::class, 'pay']);

        Route::post('/pengadaan/{procurement}/dokumen', [DocumentController::class, 'store']);
        Route::put('/pengadaan/{procurement}/dokumen/{document}', [DocumentController::class, 'update']);
        Route::delete('/pengadaan/{procurement}/dokumen/{document}', [DocumentController::class, 'destroy']);

        Route::get('/pengadaan/{procurement}/ba/baru', [BeritaAcaraController::class, 'create']);
        Route::post('/pengadaan/{procurement}/ba', [BeritaAcaraController::class, 'store']);
    });
    // Hapus pengadaan: hanya Super Admin.
    Route::delete('/pengadaan/{procurement}', [ProcurementController::class, 'destroy'])->middleware('menu:super');

    // ---- RKAP ----
    Route::get('/rkap', [RkapController::class, 'index'])->middleware('menu:rkap');
    Route::get('/rkap/{budgetCode}', [RkapController::class, 'show'])->whereNumber('budgetCode')->middleware('menu:rkap');
    Route::post('/rkap/prk/{budgetCode?}', [RkapController::class, 'save'])->middleware('menu:rkap,edit');

    // ---- Perjanjian Pelanggan ----
    Route::get('/bakb', [CustomerContractController::class, 'index'])->middleware('menu:bakb');
    Route::get('/bakb/{customerContract}', [CustomerContractController::class, 'show'])->middleware('menu:bakb');
    Route::post('/bakb', [CustomerContractController::class, 'store'])->middleware('menu:bakb,edit');
    Route::put('/bakb/{customerContract}', [CustomerContractController::class, 'update'])->middleware('menu:bakb,edit');

    // ---- PKS & Amandemen ----
    Route::get('/pks', [PksController::class, 'index'])->middleware('menu:pks');
    Route::get('/pks/{pks}', [PksController::class, 'show'])->middleware('menu:pks');
    Route::middleware('menu:pks,edit')->group(function () {
        Route::post('/pks', [PksController::class, 'store']);
        Route::put('/pks/{pks}', [PksController::class, 'update']);
        Route::post('/pks/{pks}/amandemen', [PksController::class, 'storeAmendment']);
    });

    // ---- SP2K/PO & Kontrak ----
    Route::get('/kontrak', [ContractController::class, 'index'])->middleware('menu:kontrak');
    Route::get('/kontrak/{contract}', [ContractController::class, 'show'])->middleware('menu:kontrak');
    Route::put('/kontrak/{contract}', [ContractController::class, 'update'])->middleware('menu:kontrak,edit');

    // ---- Dokumen dan Tahapan (lihat; perubahan lewat detail pengadaan) ----
    Route::get('/dokumen', [DocumentController::class, 'index'])->middleware('menu:dokumen');
    Route::get('/tahapan', [SettlementController::class, 'index'])->middleware('menu:tahapan');

    // ---- Berita Acara ----
    Route::get('/ba', [BeritaAcaraController::class, 'index'])->middleware('menu:ba');
    Route::get('/ba/{ba}', [BeritaAcaraController::class, 'show'])->whereNumber('ba')->middleware('menu:ba');
    Route::middleware('menu:ba,edit')->group(function () {
        Route::get('/ba/{ba}/edit', [BeritaAcaraController::class, 'edit']);
        Route::put('/ba/{ba}', [BeritaAcaraController::class, 'update']);
        Route::delete('/ba/{ba}', [BeritaAcaraController::class, 'destroy']);
        Route::post('/ba/{ba}/putaran', [BeritaAcaraController::class, 'newRound']);
        Route::post('/ba/{ba}/koreksi', [BeritaAcaraController::class, 'override']);
        Route::post('/ba-putaran/{round}/penandatangan', [BeritaAcaraController::class, 'addSignatory']);
        Route::put('/ba-penandatangan/{signatory}', [BeritaAcaraController::class, 'updateSignatory']);
        Route::delete('/ba-penandatangan/{signatory}', [BeritaAcaraController::class, 'removeSignatory']);
    });

    // ---- Master & Konfigurasi ----
    Route::prefix('master')->group(function () {
        Route::get('/', [MasterController::class, 'index'])->middleware('menu:master');
        Route::middleware('menu:master,edit')->group(function () {
            Route::post('/perusahaan/{company?}', [MasterController::class, 'saveCompany']);
            Route::post('/pic/{pic?}', [MasterController::class, 'savePic']);
            Route::post('/daftar/{item?}', [MasterController::class, 'saveItem']);
            Route::post('/workflow/{scheme}', [MasterController::class, 'saveWorkflow']);
            Route::post('/pengaturan', [MasterController::class, 'saveSettings']);
            Route::post('/kalender', [MasterController::class, 'saveHoliday']);
            Route::delete('/kalender/{holiday}', [MasterController::class, 'deleteHoliday']);
        });
    });

    // ---- Kelola Pengguna (Super Admin) ----
    Route::middleware('menu:super')->prefix('pengguna')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::put('/{user}', [UserController::class, 'update']);
    });
});
