<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Library\Adapters\Inbound\Http\LibraryController as Controller;

Route::get('/health', static fn () => response()->json(['status' => 'ok']));
Route::post('/auth/login', [Controller::class, 'login'])->middleware('library.json');
Route::middleware('library.auth')->group(function (): void {
    Route::get('/categories', [Controller::class, 'categories']);
    Route::get('/books', [Controller::class, 'books']);
    Route::get('/books/{id}/cover', [Controller::class, 'cover']);
    Route::get('/books/{id}', [Controller::class, 'book']);
    Route::get('/members', [Controller::class, 'members']);
    Route::post('/members', [Controller::class, 'createMember'])->middleware('library.json');
    Route::get('/members/{id}', [Controller::class, 'member']);
    Route::get('/loans/overdue', [Controller::class, 'overdue']);
    Route::get('/loans', [Controller::class, 'loans']);
    Route::post('/loans', [Controller::class, 'createLoan'])->middleware('library.json');
    Route::get('/loans/{id}', [Controller::class, 'loan']);
    Route::post('/loans/{loanId}/items/{itemId}/return', [Controller::class, 'returnItem'])->middleware('library.json');
    Route::middleware(['library.role:admin', 'library.json'])->group(function (): void {
        Route::post('/users', [Controller::class, 'createUser']);
        Route::post('/books', [Controller::class, 'createBook']);
        Route::put('/books/{id}', [Controller::class, 'updateBook']);
        Route::delete('/books/{id}', [Controller::class, 'deleteBook']);
        Route::put('/books/{id}/cover', [Controller::class, 'putCover']);
        Route::delete('/books/{id}/cover', [Controller::class, 'deleteCover']);
        Route::get('/reports/most-borrowed', [Controller::class, 'report']);
    });
});
