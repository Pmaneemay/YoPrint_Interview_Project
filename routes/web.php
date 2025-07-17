<?php

use Illuminate\Support\Facades\Route;
use App\Models\FileUpload;
use App\Events\FileStatusUpdated;
use App\Http\Controllers\FileUploadController;

Route::get('/', function () {
    return view('Homepage');
});

Route::post('/upload/init', [FileUploadController::class, 'init']);
Route::post('/upload/{id}/finish', [FileUploadController::class, 'finish']);
Route::get('/uploads', [FileUploadController::class, 'list']);

Route::get('/test-broadcast/{id}', function($id) {
    $fileUpload = FileUpload::findOrFail($id);
    event(new FileStatusUpdated($fileUpload));
    return 'Event broadcasted!';
});