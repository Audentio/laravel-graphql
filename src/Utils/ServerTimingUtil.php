<?php

namespace Audentio\LaravelGraphQL\Utils;

use BeyondCode\ServerTiming\Facades\ServerTiming;

class ServerTimingUtil
{
    public static function start(string $key): void
    {
        if (!class_exists(ServerTiming::class)) {
            return;
        }

        ServerTiming::start($key);
    }

    public static function stop(string $key): void
    {
        if (!class_exists(ServerTiming::class)) {
            return;
        }

        ServerTiming::stop($key);
    }

    public static function setDuration(string $key, float $duration): void
    {
        if (!class_exists(ServerTiming::class)) {
            return;
        }

        ServerTiming::setDuration($key, $duration);

    }
}