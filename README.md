# LaraDb

[![Unit tests](https://github.com/monicahq/laradb/actions/workflows/tests.yml/badge.svg)](https://github.com/monicahq/laradb/actions/workflows/tests.yml)
[![Database integration](https://github.com/monicahq/laradb/actions/workflows/integration.yml/badge.svg)](https://github.com/monicahq/laradb/actions/workflows/integration.yml)
[![Static analysis](https://github.com/monicahq/laradb/actions/workflows/static.yml/badge.svg)](https://github.com/monicahq/laradb/actions/workflows/static.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/monicahq/laradb.svg)](https://packagist.org/packages/monicahq/laradb)
[![License](https://img.shields.io/packagist/l/monicahq/laradb.svg)](LICENSE)

A read-only database browser you drop into a Laravel application.

Install it, open `/db`, and get your tables on the left and their rows on the
right — a small phpMyAdmin, without the server, the login screen, or the
write access.

Works with **MySQL / MariaDB**, **PostgreSQL** and **SQLite**.

It reads like a console rather than an admin panel. Every table in the schema is
in the sidebar with its row count; the grid shows the columns with their types
and their `PK` / `FK` badges, the row numbers, and `NULL` as something you can
tell apart from an empty string. Around it, the chrome tells you where you are:
the engine and its version, the database being browsed, its size and index
count, the settings the engine reports for itself, and — for the page you are
looking at — the statement that produced it and how long it took.

The page ships its own CSS and JavaScript. No build step, no Tailwind, no
Alpine, no CDN: the only remote request is a webfont, and the layout is intact
without it. Publishing the views with `--tag=laradb-views` gives you the whole
thing in one file to edit.

---

## ⚠️ Read this before you install it

LaraDb renders the contents of your database in a web page. Every row of every
table, to anyone who can reach the URL.

- **Install it with `composer require --dev`.** A production deploy running
  `composer install --no-dev` then never ships the viewer at all.
- **It is disabled outside the `local` environment by default.** Turning it on
  anywhere else is an explicit decision you have to make.
- **Never expose it without authentication and authorisation.** The default
  middleware stack is `['web', 'auth']`, which is the bare minimum — any
  logged-in user passes it. Add a gate (see [Locking it down](#locking-it-down)).
- **Do not enable it on a database holding personal or otherwise sensitive
  data** unless access is strictly controlled. There is no column masking, no
  redaction and no audit log in v1.
- It never writes: the routes are `GET` only and the package issues nothing but
  `SELECT` statements. That protects your data from corruption, not from being
  read by the wrong person.

---

## Installation

Install it as a **development dependency**:

```bash
composer require --dev monicahq/laradb
```

The service provider is auto-discovered. In a `local` environment, that is all
you need: visit `/db`.

Publish the config to change anything:

```bash
php artisan vendor:publish --tag=laradb-config
```

The views can be published too, if you want to restyle them:

```bash
php artisan vendor:publish --tag=laradb-views
```

## Usage

Simply go to `/db` URL in your project, and the screen will shown. You can
configure this URL - see section below.

## Configuration

`config/laradb.php`:

| Key | Default | What it does |
| --- | --- | --- |
| `enabled` | `null` | `null` means "only in `local`". Set `true`/`false` (or `LARADB_ENABLED`) to decide explicitly. |
| `route_prefix` | `'db'` | Where the viewer is mounted. |
| `middleware` | `['web', 'auth']` | The middleware stack applied to both routes. |
| `connection` | `null` | The connection to browse, as named in `config/database.php`. `null` uses the default one. |
| `per_page` | `25` | Rows per page. |
| `max_cell_length` | `120` | Long values are truncated to this many characters, full value in the tooltip. `0` disables it. |

Each has an environment variable: `LARADB_ENABLED`, `LARADB_ROUTE_PREFIX`,
`LARADB_CONNECTION`, `LARADB_PER_PAGE`, `LARADB_MAX_CELL_LENGTH`.

## Locking it down

`auth` alone only proves someone is logged in. Add a gate:

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewLaraDb', function ($user) {
        return $user->is_admin;
    });
}
```

```php
// config/laradb.php
'middleware' => ['web', 'auth', 'can:viewLaraDb'],
```

If you enable it outside `local`, put it behind a prefix that is not guessable
and keep the gate narrow:

```env
LARADB_ENABLED=true
LARADB_ROUTE_PREFIX=internal/db-2f9c
```

## Routes

Both routes are named and `GET` only.

| Route | Name | Returns |
| --- | --- | --- |
| `GET /db` | `laradb.index` | The full page. `?table=` selects a table, `?page=` a page. |
| `GET /db/tables/{table}` | `laradb.table` | The rows of one table, as an HTML fragment. |

The fragment endpoint also speaks JSON, with `?format=json` or an
`Accept: application/json` header:

```json
{
  "table": "users",
  "columns": [
    {"name": "id", "type": "integer", "nullable": false, "primary_key": true, "default": null, "foreign_key": null},
    {"name": "account_id", "type": "integer", "nullable": true, "primary_key": false, "default": null, "foreign_key": "accounts.id"}
  ],
  "rows": [{"id": 1, "account_id": 3, "name": "Ada"}],
  "page": 1,
  "per_page": 25,
  "total": 42,
  "last_page": 2,
  "sql": "SELECT * FROM \"users\" LIMIT 25 OFFSET 0",
  "duration_ms": 0.42
}
```

## How it stays read-only

The interesting part of a database browser is what it refuses to do.

- **No user input ever reaches SQL.** Table names are matched against the list
  returned by the schema introspection before anything is built. A name that is
  not in that whitelist raises `UnknownTableException`, which the controller
  turns into a 404.
- **Identifiers are quoted per engine** — backticks on MySQL, double quotes on
  PostgreSQL and SQLite, with the inner quote doubled — so a table named
  `we"ird` cannot break out of an identifier.
- **`LIMIT` and `OFFSET` are integers** derived from arithmetic, never
  interpolated strings.
- **There is no query box.** The package cannot run arbitrary SQL because it has
  nowhere to accept it.
- **Connection errors are sanitised.** PDO messages quote the DSN and the
  database user, so the page shows `Could not open the [x] connection.` and the
  real error goes to your log.
- **Reads go to the read replica** when the connection defines one
  (`getReadPdo()`).
- **The database path is shortened.** SQLite reports an absolute file path; the
  header shows it relative to the project root, or as the bare file name when
  it lives outside. Where your server keeps its files is not the page's story
  to tell.

## Row counts

The sidebar shows an *estimate*: `information_schema.tables.TABLE_ROWS` on
MySQL, `pg_class.reltuples` on PostgreSQL. That keeps listing a large schema
cheap, and it is why the number can drift — and why a PostgreSQL table that has
never been `ANALYZE`d shows no count at all rather than a wrong one. SQLite,
whose databases are usually small, counts exactly.

The number driving the pagination is always an exact `COUNT(*)` on the selected
table.

## Using the core without Laravel

The reading side depends on nothing but PDO, so it works anywhere:

```php
use LaraDb\DriverFactory;

$pdo = new PDO('sqlite:database.sqlite');
$driver = DriverFactory::fromPdo($pdo);

foreach ($driver->listTables() as $table) {
    echo $table->name, "\n";
}

$page = $driver->getRows('users', page: 2, perPage: 25);
```

`DriverInterface` is the package's public contract:

```php
// Reading
public function listTables(): array;                                     // TableInfo[]
public function getColumns(string $table): array;                        // ColumnInfo[]
public function getRowCount(string $table): int;
public function getRows(string $table, int $page, int $perPage): TablePage;
public function name(): string;

// Describing — everything the chrome is built from
public function serverVersion(): ?string;
public function databaseName(): ?string;      // the db name, or the sqlite file
public function sizeInBytes(): ?int;
public function indexCount(): ?int;
public function metadata(): array;            // engine settings, in display order
public function getForeignKeys(string $table): array;  // column => "table.column"
public function describe(): DatabaseInfo;     // all of the above, gathered once
public function queryCount(): int;
```

The describing half is nullable throughout, and deliberately so: reading a
system catalogue is a privilege. A connection whose user cannot do it gets a
working viewer with a quieter header, never a 500.

What each engine reports for `metadata()`:

| | Keys |
| --- | --- |
| SQLite | `page`, `journal`, `enc`, `fk`, `schema` |
| MySQL | `engine`, `charset`, `collation` |
| PostgreSQL | `enc`, `collation`, `schema` |

Any change to `DriverInterface` is a breaking change and gets a major version
bump.

## Testing

```bash
composer test          # Pint, PHPStan and PHPUnit
composer test:unit     # PHPUnit only
```

The unit and feature suites run on in-memory SQLite and need nothing installed.
The MySQL and PostgreSQL suites skip themselves unless a server is reachable;
point them at one with `LARADB_MYSQL_DSN` / `LARADB_PGSQL_DSN` (plus the
matching `_USERNAME` and `_PASSWORD`). CI runs them against real services on
every push.

## Roadmap

Deliberately out of scope for v1: column search and filters, sorting, following
a foreign key through to the row it points at (the badge names the target, but
it is not a link), CSV/JSON export, row editing behind a permission, dark mode,
and picking between several configured connections from the UI.

## Contributing

Bug reports and pull requests are welcome. Please keep Pint and PHPStan green.

## Changelog

Releases and their notes are generated by
[semantic-release](https://github.com/semantic-release/semantic-release) from
the commit history. See the
[releases page](https://github.com/monicahq/laradb/releases).

## License

MIT. See [LICENSE](LICENSE).
