<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Enums;

enum ValueState
{
    case REQUIRED;
    case OPTIONAL;
    case DEFAULTED;
}
