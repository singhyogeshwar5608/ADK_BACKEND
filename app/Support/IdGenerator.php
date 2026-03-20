<?php

namespace App\Support;

use Illuminate\Support\Str;

class IdGenerator
{
    public static function memberId(): string
    {
        $segment = Str::upper(Str::random(8));
        return 'MBR-' . $segment;
    }
}
