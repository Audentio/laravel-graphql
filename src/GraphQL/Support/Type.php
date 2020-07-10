<?php

namespace Audentio\LaravelGraphQL\GraphQL\Support;

use \Rebing\GraphQL\Support\Type as GraphQLType;

abstract class Type extends GraphQLType
{
    public function fields(): array
    {
        return $this->getResource()->getTypeFields();
    }

    protected function getResource(): Resource
    {
        $className = $this->getResourceClass();
        return new $className;
    }

    abstract protected function getResourceClass(): string;
}