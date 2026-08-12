<?php

namespace App\Domain\Customers\Enums;

enum SavedLocationLabel: string
{
    case Home = 'home';
    case Work = 'work';
    case Custom = 'custom';
}
