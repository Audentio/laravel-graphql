<?php

namespace Audentio\LaravelGraphQL\GraphQL\Console\Traits;

use Audentio\LaravelBase\Traits\ExtendConsoleCommandTrait;

trait GraphQLConsoleTrait
{
    use ExtendConsoleCommandTrait;

    protected function getDataType($name, $suffix)
    {
        preg_match('/([^\\\]+)$/', $name, $matches);

        $name = substr($matches[1], 0, (-1 * strlen($suffix)));

        if ($prefix = config('audentioGraphQL.namePrefix')) {
            $name = substr($name, strlen($prefix));
        }

        return $name;
    }

    protected function replaceTypeClass($stub)
    {
        if (class_exists('App\GraphQL\Definitions\Type')) {
            $stub = str_replace('Audentio\LaravelGraphQL\GraphQL\Definitions\Type', 'App\GraphQL\Definitions\Type', $stub);
        }

        return $stub;
    }

    public function normalizeTypeName(string $name, ?string $suffix = null, ?string $extraPrefix = null): string
    {
        if ($suffix) {
            $name = $this->suffixCommandClass($name, $suffix);
        }

        $prefix = config('audentioGraphQL.namePrefix') ?? '';
        if ($extraPrefix) {
            $prefix = $prefix . $extraPrefix;
        }

        if ($prefix) {
            $name = $this->prefixCommandClass($name, $prefix);
        }

        return $name;
    }
}