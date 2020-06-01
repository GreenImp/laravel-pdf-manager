<?php

namespace GreenImp\PdfManager\Enums;

use BenSampo\Enum\Enum;

final class PagesEnum extends Enum
{
    public const ALL = 'all';
    public const FIRST = 'first';
    public const LAST = 'last';
    public const EVEN = 'even';
    public const ODD = 'odd';
}
