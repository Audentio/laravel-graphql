<?php

namespace Audentio\LaravelGraphQL\GraphQL\Debugger;

class QueriesExecutedDebugger implements \Countable
{
    protected array $queryList = [];

    /**
     * @param \Illuminate\Database\Events\QueryExecuted $query
     */
    public function push($query): void
    {
        $bindings = $query->bindings;
        foreach ($bindings as &$binding) {
            if ($binding instanceof \DateTime) {
                $binding = $binding->getTimestamp();
            }
        }

        $this->queryList[] = [
            'sql' => $query->sql,
            'bindings' => '[' . implode(', ', $bindings) . ']',
            'time' => $query->time,
            'connection_name' => $query->connectionName
        ];
    }

    public function count(): int
    {
        return count($this->queryList);
    }

    public function time(): float
    {
        $time = 0.0;
        foreach ($this->queryList as $query) {
            $time += $query['time'];
        }

        return $time;
    }

    /**
     * @return array
     */
    public function all(): array
    {
        return $this->queryList;
    }

}
