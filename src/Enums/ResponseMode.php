<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Enums;

enum ResponseMode: int
{
    case NoQueries = 0;
    case WithQueries = 1;
}
