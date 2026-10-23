<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Enums;

enum CallbackMode: int
{
    case None = 0;
    case Summary = 1;
    case Detail = 2;
}
