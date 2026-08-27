# LaraDb

[![Unit tests](https://github.com/monicahq/laradb/actions/workflows/tests.yml/badge.svg)](https://github.com/monicahq/laradb/actions/workflows/tests.yml)
[![Database integration](https://github.com/monicahq/laradb/actions/workflows/integration.yml/badge.svg)](https://github.com/monicahq/laradb/actions/workflows/integration.yml)
[![Static analysis](https://github.com/monicahq/laradb/actions/workflows/static.yml/badge.svg)](https://github.com/monicahq/laradb/actions/workflows/static.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/monicahq/laradb.svg)](https://packagist.org/packages/monicahq/laradb)
[![License](https://img.shields.io/packagist/l/monicahq/laradb.svg)](LICENSE)

A read-only database browser you drop into a Laravel application. Install it,
open `/db`, and get your tables on the left and their rows on the right — a
small phpMyAdmin, without the server, the login screen, or the write access.

Works with **MySQL / MariaDB**, **PostgreSQL** and **SQLite**.

<!-- TODO: add art/screenshot.png and uncomment
![Screenshot](art/screenshot.png)
-->

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

`--dev` matters. A production deploy running `composer install --no-dev` then
leaves the viewer out of the build entirely — its code is not on the server, so
there is nothing to misconfigure, no route to reach and no flag to get wrong.
That is a stronger guarantee than the `enabled` switch, which is only a second
line of defence.

If you deliberately want it on a deployed environment — a staging box, an
internal admin tool — move it to `require` instead, and treat
[Locking it down](#locking-it-down) as mandatory rather than advisory:

```bash
composer require monicahq/laradb
```

The package behaves identically either way; the difference is only whether the
code ships.

Publish the config to change anything:

```bash
php artisan vendor:publish --tag=laradb-config
```

The views can be published too, if you want to restyle them:

```bash
php artisan vendor:publish --tag=laradb-views
```

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
  "columns": [{"name": "id", "type": "integer", "nullable": false, "primary_key": true, "default": null}],
  "rows": [{"id": 1, "name": "Ada"}],
  "page": 1,
  "per_page": 25,
  "total": 42,
  "last_page": 2
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
public function listTables(): array;                                     // TableInfo[]
public function getColumns(string $table): array;                        // ColumnInfo[]
public function getRowCount(string $table): int;
public function getRows(string $table, int $page, int $perPage): TablePage;
public function name(): string;
```

Any change to it is a breaking change and gets a major version bump.

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

Deliberately out of scope for v1: column search and filters, sorting, foreign
key navigation, CSV/JSON export, row editing behind a permission, dark mode, and
picking between several configured connections from the UI.

## Contributing

Bug reports and pull requests are welcome. Please keep Pint and PHPStan green.

## Changelog

Releases and their notes are generated by
[semantic-release](https://github.com/semantic-release/semantic-release) from
the commit history. See the
[releases page](https://github.com/monicahq/laradb/releases).

## License

MIT. See [LICENSE](LICENSE).
