<?php

namespace GreenImp\PdfManager\Enums;

use BenSampo\Enum\Enum;

final class PagePositionEnum extends Enum
{
    public const BOTTOM_LEFT = 'LB';
    public const BOTTOM_CENTRE = 'CB';
    public const BOTTOM_RIGHT = 'RB';

    public const TOP_LEFT = 'LT';
    public const TOP_CENTRE = 'CT';
    public const TOP_RIGHT = 'RT';

    public const MIDDLE_LEFT = 'LM';
    public const MIDDLE_CENTRE = 'CM';
    public const MIDDLE_RIGHT = 'RM';
}
