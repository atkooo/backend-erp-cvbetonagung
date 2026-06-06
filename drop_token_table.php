<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

Schema::dropIfExists('personal_access_tokens');
DB::table('migrations')->where('migration', 'like', '%personal_access_tokens%')->delete();

echo "Table dropped and migration record removed.\n";
