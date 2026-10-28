<?php

use App\Http\Controllers\PostbackController;
use Illuminate\Support\Facades\Route;

Route::get('/postback', [PostbackController::class, 'receive']);
Route::get('/postbacks', [PostbackController::class, 'index']);
