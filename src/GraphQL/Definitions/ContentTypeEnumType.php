<?php

namespace Audentio\LaravelGraphQL\GraphQL\Definitions;

use Audentio\LaravelBase\Utils\ContentTypeUtil;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Utils\MixedStore;
use GraphQL\Utils\Utils;

class ContentTypeEnumType extends EnumType
{
    /** @var MixedStore<mixed, EnumValueDefinition> */
    protected $valueLookup;

    public function serialize($value)
    {
        $value = ContentTypeUtil::getFriendlyContentTypeName($value);

        $lookup = $this->getValueLookup();
        if (isset($lookup[$value])) {
            return $lookup[$value]->name;
        }

        throw new Error('Cannot serialize value as enum: ' . Utils::printSafe($value));
    }

    protected function getValueLookup()
    {
        if ($this->valueLookup === null) {
            $this->valueLookup = new MixedStore();

            foreach ($this->getValues() as $valueName => $value) {
                $this->valueLookup->offsetSet($value->value, $value);
            }
        }

        return $this->valueLookup;
    }
}