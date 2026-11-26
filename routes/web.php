<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/mou/{sekolah}/download', [\App\Http\Controllers\MouDownloadController::class, 'download'])
    ->middleware(['auth', 'web'])
    ->name('mou.download');
