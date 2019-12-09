<?php

namespace Audentio\LaravelGraphQL\Illuminate\Foundation\Console;

class ModelMakeCommand extends \Audentio\LaravelBase\Illuminate\Foundation\Console\ModelMakeCommand
{
    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);

        $sub = <<<EOF
    public static function getOutputFields(): array
    {
        \$fields = [];

        return \$fields;
    }

    public static function getCommonFields(bool \$update = false): array
    {
        \$fields = [];

        return \$fields;
    }

    public static function getInputFields(bool \$update = false): array
    {
        \$fields = [];

        return \$fields;
    }
EOF;

        $stub = str_replace('//', '//' . "\n\n" . $sub, $stub);

        return $stub;
    }
}