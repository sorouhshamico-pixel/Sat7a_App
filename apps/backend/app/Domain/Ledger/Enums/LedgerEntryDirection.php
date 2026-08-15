<?php

namespace App\Domain\Ledger\Enums;

enum LedgerEntryDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
