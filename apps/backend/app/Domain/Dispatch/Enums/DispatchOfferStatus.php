<?php

namespace App\Domain\Dispatch\Enums;

enum DispatchOfferStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    // Automatically closed out because a different offer for the same
    // order was accepted first — see
    // App\Domain\Dispatch\Actions\AcceptDispatchOfferAction.
    case Superseded = 'superseded';
}
