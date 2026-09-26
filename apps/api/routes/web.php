<?php
use Illuminate\Support\Facades\Route;
Route::get('/', fn () => response()->json(['service' => 'meeting-intelligence-api']));
