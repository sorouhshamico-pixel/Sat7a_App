<?php

namespace App\Domain\Orders\Enums;

enum OrderCancelledBy: string
{
    case Customer = 'customer';
    case Provider = 'provider';
    case Admin = 'admin';
    case System = 'system';
}
