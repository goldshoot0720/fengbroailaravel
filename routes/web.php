<?php

use App\Livewire\Workspace;
use Illuminate\Support\Facades\Route;

// 網址維持 index.php?page= ，既有相對路徑（assets、api.php、uploads）才不會在子目錄部署時失效。
Route::match(['GET', 'HEAD', 'POST'], '/', Workspace::class);
Route::match(['GET', 'HEAD', 'POST'], '/index.php', Workspace::class);
