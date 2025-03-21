<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReservationController;

Route::get('/', function () { return view('welcome'); });
Route::get('reservations/create', [ReservationController::class, 'create'])->name('reservations.create');
Route::post('reservations', [ReservationController::class, 'store'])->name('reservations.store');
Route::get('reservations', [ReservationController::class, 'index'])->name('reservations.index');
Route::delete('reservations/{reservation}', [ReservationController::class, 'destroy'])->name('reservations.destroy');
