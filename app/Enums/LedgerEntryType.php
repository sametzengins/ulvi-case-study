<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case SignupBonus = 'signup_bonus';
    case MessageHold = 'message_hold';
    case MessageRefund = 'message_refund';
    case TopUp = 'top_up';
    case Adjustment = 'adjustment';
}
