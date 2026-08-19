<?php
use Illuminate\Support\Facades\Artisan;
Artisan::command('meeting:status', function () {
    $this->info('Meeting Intelligence API is available.');
})->purpose('Show application status');
