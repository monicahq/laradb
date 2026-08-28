<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | When null, LaraDb only registers its routes in the "local" environment.
    | This is the safe default: the viewer exposes the content of your database
    | and should never appear in production by accident. Set it to true (or
    | LARADB_ENABLED=true) to enable it elsewhere — and make sure the
    | middleware below actually protects it when you do.
    |
    */

    'enabled' => env('LARADB_ENABLED', null),

    /*
    |--------------------------------------------------------------------------
    | Route prefix
    |--------------------------------------------------------------------------
    |
    | The URI the viewer is mounted on. Defaults to "/db".
    |
    */

    'route_prefix' => env('LARADB_ROUTE_PREFIX', 'db'),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | The middleware stack applied to every LaraDb route. There is deliberately
    | no public default: "auth" is included so the viewer is never reachable
    | anonymously. In a real application you want an authorisation check on top
    | of authentication, for example:
    |
    |     'middleware' => ['web', 'auth', 'can:viewLaraDb'],
    |
    | Setting this to null or [] does not publish the viewer unprotected: it
    | falls back to ['web', 'auth']. If you really want it reachable without
    | authentication, say so explicitly with ['web'].
    |
    */

    'middleware' => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Connection
    |--------------------------------------------------------------------------
    |
    | The database connection to browse, as named in config/database.php.
    | Leave it null to use the application's default connection.
    |
    */

    'connection' => env('LARADB_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Rows per page
    |--------------------------------------------------------------------------
    */

    'per_page' => (int) env('LARADB_PER_PAGE', 25),

    /*
    |--------------------------------------------------------------------------
    | Maximum cell length
    |--------------------------------------------------------------------------
    |
    | Long text values are truncated to this many characters in the table, with
    | the full value available in the cell's tooltip. Set to 0 to disable.
    |
    */

    'max_cell_length' => (int) env('LARADB_MAX_CELL_LENGTH', 120),

];
