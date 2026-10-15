<?php

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
    // ---- Baca (semua peran) ----
    Route::get('/', DashboardController::class);
    Route::get('/pengadaan', [ProcurementController::class, 'index']);
    Route::get('/rkap', [RkapController::class, 'index']);
    Route::get('/rkap/{budgetCode}', [RkapController::class, 'show'])->whereNumber('budgetCode');
    Route::get('/pengadaan/baru', [ProcurementController::class, 'create'])->middleware('can:edit');
    Route::get('/pengadaan/{procurement}', [ProcurementController::class, 'show']);
    Route::get('/pengadaan/{procurement}/edit', [ProcurementController::class, 'edit'])->middleware('can:edit');
    Route::get('/kontrak', [ContractController::class, 'index']);
    Route::get('/kontrak/{contract}', [ContractController::class, 'show']);
    Route::get('/bakb', [CustomerContractController::class, 'index']);
    Route::get('/bakb/{customerContract}', [CustomerContractController::class, 'show']);
    Route::get('/pks', [PksController::class, 'index']);
    Route::get('/pks/{pks}', [PksController::class, 'show']);
    Route::get('/ba', [BeritaAcaraController::class, 'index']);
    Route::get('/ba/{ba}', [BeritaAcaraController::class, 'show'])->whereNumber('ba');
    Route::get('/dokumen', [DocumentController::class, 'index']);
    Route::get('/tahapan', [SettlementController::class, 'index']);
    Route::get('/ekspor/pengadaan', [ExportController::class, 'procurements']);

    // ---- Ubah (admin & pengaju) ----
    Route::middleware('can:edit')->group(function () {
        Route::post('/pengadaan', [ProcurementController::class, 'store']);
        Route::put('/pengadaan/{procurement}', [ProcurementController::class, 'update']);
        Route::post('/pengadaan/{procurement}/batal', [ProcurementController::class, 'cancel']);
        Route::post('/pengadaan/{procurement}/aktifkan', [ProcurementController::class, 'reactivate']);

        Route::post('/pengadaan/{procurement}/proses', [ProcessController::class, 'store']);
        Route::post('/pengadaan/{procurement}/proses/buka', [ProcessController::class, 'reopen']);

        Route::post('/pengadaan/{procurement}/kontrak', [ContractController::class, 'store']);
        Route::post('/pengadaan/{procurement}/kontrak-pengadaan', [ContractController::class, 'storeWork']);
        Route::put('/kontrak/{contract}', [ContractController::class, 'update']);
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
        Route::get('/ba/{ba}/edit', [BeritaAcaraController::class, 'edit']);
        Route::put('/ba/{ba}', [BeritaAcaraController::class, 'update']);
        Route::delete('/ba/{ba}', [BeritaAcaraController::class, 'destroy']);
        Route::post('/ba/{ba}/putaran', [BeritaAcaraController::class, 'newRound']);
        Route::post('/ba/{ba}/koreksi', [BeritaAcaraController::class, 'override']);
        Route::post('/ba-putaran/{round}/penandatangan', [BeritaAcaraController::class, 'addSignatory']);
        Route::put('/ba-penandatangan/{signatory}', [BeritaAcaraController::class, 'updateSignatory']);
        Route::delete('/ba-penandatangan/{signatory}', [BeritaAcaraController::class, 'removeSignatory']);

        Route::post('/rkap/prk/{budgetCode?}', [RkapController::class, 'save']);

        Route::post('/bakb', [CustomerContractController::class, 'store']);
        Route::put('/bakb/{customerContract}', [CustomerContractController::class, 'update']);
        Route::post('/pks', [PksController::class, 'store']);
        Route::put('/pks/{pks}', [PksController::class, 'update']);
        Route::post('/pks/{pks}/amandemen', [PksController::class, 'storeAmendment']);
    });

    // ---- Master & Konfigurasi (admin) ----
    Route::middleware('can:admin')->prefix('master')->group(function () {
        Route::get('/', [MasterController::class, 'index']);
        Route::post('/perusahaan/{company?}', [MasterController::class, 'saveCompany']);
        Route::post('/pic/{pic?}', [MasterController::class, 'savePic']);
        Route::post('/daftar/{item?}', [MasterController::class, 'saveItem']);
        Route::post('/workflow/{scheme}', [MasterController::class, 'saveWorkflow']);
        Route::post('/pengaturan', [MasterController::class, 'saveSettings']);
        Route::post('/kalender', [MasterController::class, 'saveHoliday']);
        Route::delete('/kalender/{holiday}', [MasterController::class, 'deleteHoliday']);
        Route::post('/pengguna/{user}', [MasterController::class, 'saveUser']);
    });
});
