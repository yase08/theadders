<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('storage/kocak/{pathFilename}', function ($pathFilename) {
    $path = storage_path('app/public/' . $pathFilename);

    if (file_exists($path)) {
        return response()->file($path);
    }

    abort(404);
});
