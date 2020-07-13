<?php

namespace Audentio\LaravelGraphQL\GraphQL\Support;

use Audentio\LaravelGraphQL\GraphQL\Support\Resource as BaseResource;
use \Rebing\GraphQL\Support\Type as GraphQLType;

abstract class Type extends GraphQLType
{
    public function fields(): array
    {
        return $this->getResource()->getTypeFields();
    }

    protected function getResource(): BaseResource
    {
        $className = $this->getResourceClassName();
        return new $className;
    }

    abstract protected function getResourceClassName(): string;
}