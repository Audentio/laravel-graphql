<?php

namespace Audentio\LaravelGraphQL\GraphQL\Definitions;

use Carbon\Carbon;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;

class TimestampType extends ScalarType
{
    private static $instance = null;

    /**
     * @var string
     */
    public $name = "Timestamp";

    /**
     * @var string
     */
    public $description = "Conversion of Carbon object into Atom timestamp";

    public function __construct()
    {
        parent::__construct();

        Utils::invariant($this->name, 'Type must be named.');
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

    protected function toAtomString($value)
    {
        if ($value instanceof StringValueNode) {
            $value = $value->value;
        }

        if (!$value instanceof Carbon) {
            $value = new Carbon($value);
        }

        return $value->toAtomString();
    }
}