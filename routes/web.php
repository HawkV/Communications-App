<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\BitrixController;
use App\Helpers\BitrixApi;

use App\Models\Column;
use App\Models\ColumnSetting;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::middleware(['connect'])->group(function () {
    Route::post('/data',  [BitrixController::class, 'getCompanies']);

    Route::post('/', [BitrixController::class, 'show']);

    Route::post('/settings', [BitrixController::class, 'updateHeaderSettings']);    
    
    Route::post('/headers', [BitrixController::class, 'addHeader']);
});

Route::get('/test', function () {
    return 'test1';
});