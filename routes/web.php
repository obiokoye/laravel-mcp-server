<?php

use App\Http\Controllers\ConsoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ConsoleController::class, 'index'])->name('console');
Route::post('/console/ask', [ConsoleController::class, 'ask'])->middleware('throttle:30,1')->name('console.ask');
