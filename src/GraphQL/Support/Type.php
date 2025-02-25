<?php

namespace Audentio\LaravelGraphQL\GraphQL\Support;

use Audentio\LaravelGraphQL\GraphQL\Support\Resource as BaseResource;
use Rebing\GraphQL\Support\Type as GraphQLType;

abstract class Type extends GraphQLType
{
    public function fields(): array
    {
        if (!$this->getResource()) {
            throw new \RuntimeException('Type ' . $this->attributes['name'] . ' does not have an associated ' .
                'resource.');
        }

        return $this->getResource()->getTypeFields();
    }

    protected function getResource(): ?BaseResource
    {
        if (!$this->getResourceClassName()) {
            return null;
        }

        return Resource::getResourceInstance($this->getResourceClassName());
    }

    abstract protected function getResourceClassName(): ?string;

    public function __construct()
    {
        if (config('audentioGraphQL.enableTypeModel')) {
            if ($resource = $this->getResource()) {
                $modelClass = $resource->getExpectedModelClass();

                if ($modelClass && !array_key_exists('model', $this->attributes)) {
                    $this->attributes['model'] = $modelClass;
                }
            }
        }
    }
}