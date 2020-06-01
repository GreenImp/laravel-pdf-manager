<?php

namespace GreenImp\PdfManager\Enums;

use BenSampo\Enum\Enum;

final class FieldModifierEnum extends Enum
{
    public const DELETE = 'delete';
    public const FLATTEN = 'flatten';
    public const READ_ONLY = 'readonly';
    public const REQUIRED = 'required';
}
