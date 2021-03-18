<?php

namespace Audentio\LaravelGraphQL\GraphQL\Debugger;

class QueriesExecutedDebugger implements \Countable
{
    protected $queryList = [];

    /**
     * @param \Illuminate\Database\Events\QueryExecuted $query
     */
    public function push($query)
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

    public function count()
    {
        return count($this->queryList);
    }

    public function time()
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
    public function all()
    {
        return $this->queryList;
    }

}