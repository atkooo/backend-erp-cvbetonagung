<?php

namespace App\Enums;

enum SalesOrderStatus: string
{
    case Draft = 'draft';
    case Processing = 'processing';
    case Approved = 'approved';
    case PendingDelivery = 'pending_delivery';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
