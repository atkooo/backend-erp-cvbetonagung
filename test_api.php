<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$req = \Illuminate\Http\Request::create('/api/sales/sales-orders?include=deliveryOrders.items', 'GET');
$controller = $app->make(\App\Http\Controllers\Api\SalesController::class);
$resp = $controller->index($req, 'sales-orders');
echo json_encode($resp->getData(true)['data'][0]);
