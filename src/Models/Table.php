<?php

namespace LaraDb\Models;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Table
{
    public function __construct(
        public string $name
    )
    { }

    public function getRouteURL(): string
    {
        return route('db.index', ['table' => $this->name]);
    }

    public function getColumnValues(int $offset = 0, int $limit = 300): Collection
    {
        $columns = collect(Schema::getColumns($this->name));
        $rows = DB::table($this->name)
            ->limit($limit)
            ->offset($offset)
            ->get();

        $rowsCollection = collect();

        // we add the column names as the first row
        $columnsCollection = $columns->map(fn ($column) => [
            'value' => $column['name']
        ]);
        $rowsCollection->push($columnsCollection);

        // then we add the actual data
        foreach ($rows as $row) {
            $columnsCollection = $columns->map(fn ($column) => [
                'value' => $row->{$column['name']}
            ]);
            $rowsCollection->push($columnsCollection);
        }

        return $rowsCollection;
    }
}
