<?php

namespace App\Enums;

/** G11: how an optional field with no value is presented. */
enum OptionalFieldMode: string
{
    case Omit = 'omit';
    case Null = 'null';
}
