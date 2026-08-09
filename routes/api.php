<?php

/*
|--------------------------------------------------------------------------
| API Routes — prefix configured in bootstrap/app.php as api/v1
|--------------------------------------------------------------------------
|
| Split by domain to keep this file small. Public URL paths are unchanged.
|
*/

require __DIR__.'/api/auth.php';
require __DIR__.'/api/public.php';
require __DIR__.'/api/payment.php';
require __DIR__.'/api/user.php';
require __DIR__.'/api/exam.php';
require __DIR__.'/api/admin.php';
