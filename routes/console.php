<?php

use Illuminate\Support\Facades\Artisan;

// डिफ़ॉल्ट कमेंट (इसे ऐसा ही छोड़ दें)
Artisan::command('inspire', function () {
    $this->comment(Illuminate\Foundation\Inspiring::quote());
})->purpose('Display an inspiring quote');
