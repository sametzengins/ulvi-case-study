<?php

namespace App\Enums;

enum TopUpStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
}
