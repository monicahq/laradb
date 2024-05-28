<?php

namespace LaraDb\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use LaraDb\Helpers\DatabaseViewHelper;
use LaraDb\Models\Table;

class DBController extends Controller
{
    public function index(Request $request)
    {
        $tables = Schema::getTables();

        if (($table = $request->input('table')) !== null) {
            // TODO : check if table is part of existing tables
            $firstTable = new Table($table);
        } else {
            $firstTable = new Table($tables[0]['name']);
        }

        $tablesCollection = DatabaseViewHelper::getTablesInformation($tables);
        $rows = $firstTable->getColumnValues(0);

        return view('laradb::table', [
            'database' => DatabaseViewHelper::getDatabaseInformation(),
            'tables' => $tablesCollection,
            'rows' => $rows,
            'requestedTable' => $firstTable->name,
        ]);
    }
}
