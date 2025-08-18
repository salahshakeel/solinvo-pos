<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SalesController;


Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/search', [ProductController::class, 'search']);
Route::post('/sales', [SalesController::class, 'store']);
Route::get('/sales', [SalesController::class, 'index']);
Route::post('/upload-csv', [ProductController::class, 'uploadCsv']);