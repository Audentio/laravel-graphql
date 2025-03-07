<?php

namespace Audentio\LaravelGraphQL\GraphQL\Definitions;

use Carbon\Carbon;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;

class TimestampType extends ScalarType
{
    private static ?self $instance = null;

    public string $name = "Timestamp";

    public ?string $description = "Conversion of Carbon object into Atom timestamp";

    public function __construct(string $name = 'Timestamp')
    {
        if(!$name) {
            throw new InvariantViolation('Type must be named.');
        }

        $this->name = $name;
        parent::__construct();
    }

    static public function type()
    {
        if(!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __clone() {}

    public function serialize($value)
    {
        return $this->toAtomString($value);
    }

    public function parseValue($value)
    {
        return $this->toAtomString($value);
    }

    public function parseLiteral($valueNode, ?array $variables = null)
    {
        return $this->toAtomString($valueNode);
    }

    public function toType(): Type
    {
        return static::type();
    }

    protected function toAtomString($value)
    {
        if (strlen($value) <= 1) {
            return null;
        }

        if ($value instanceof StringValueNode) {
            $value = $value->value;
        }

        try {
            if (!$value instanceof Carbon) {
                $value = new Carbon($value);
            }
        } catch (\Exception $e) {
            return null;
        }

        return $value;
    }
}