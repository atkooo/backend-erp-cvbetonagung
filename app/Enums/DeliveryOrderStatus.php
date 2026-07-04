<?php

namespace App\Enums;

enum DeliveryOrderStatus: string
{
    case Draft = 'draft';
    case ReadyToLoad = 'ready_to_load';
    case Shipped = 'shipped';
    case Received = 'received';
    case Cancelled = 'cancelled';
}
