<?php

declare(strict_types=1);

namespace LaraDb\Http\Controllers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LaraDb\Drivers\DriverInterface;
use LaraDb\DTO\DatabaseInfo;
use LaraDb\DTO\TableInfo;
use LaraDb\Exceptions\ConnectionFailedException;
use LaraDb\Exceptions\LaraDbException;
use LaraDb\Exceptions\UnknownTableException;
use LaraDb\Support\DatabasePath;
use LaraDb\Support\Runtime;
use LaraDb\Support\ValuePresenter;
use Throwable;

/**
 * The whole HTTP surface of the package: a page, and the fragment it swaps in
 * when you click another table.
 */
final class LaraDbController
{
    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
        private readonly ViewFactory $views,
    ) {}

    /**
     * The full page: the table list, plus the first page of the selected table.
     */
    public function index(Request $request): View|Response
    {
        try {
            $driver = $this->driver();
            $tables = $driver->listTables();
        } catch (LaraDbException $e) {
            return $this->connectionError($e);
        }

        $selected = $this->resolveSelectedTable($request, $tables);

        if ($selected === null) {
            return $this->view('laradb::index', [
                'tables' => $tables,
                'selected' => null,
                'page' => null,
                'error' => null,
                'database' => $this->describe($driver),
                'queries' => $driver->queryCount(),
            ]);
        }

        try {
            $page = $driver->getRows($selected, $this->page($request), $this->perPage());
        } catch (UnknownTableException) {
            abort(404, 'Unknown table.');
        } catch (LaraDbException $e) {
            return $this->view('laradb::index', [
                'tables' => $tables,
                'selected' => $selected,
                'page' => null,
                'error' => $e->getMessage(),
                'database' => $this->describe($driver),
                'queries' => $driver->queryCount(),
            ]);
        }

        return $this->view('laradb::index', [
            'tables' => $tables,
            'selected' => $selected,
            'page' => $page,
            'error' => null,
            'database' => $this->describe($driver),
            'queries' => $driver->queryCount(),
        ]);
    }

    /**
     * One table's rows. Returns the HTML fragment Alpine swaps into the page,
     * or JSON when the client asks for it.
     */
    public function show(Request $request, string $table): View|JsonResponse|Response
    {
        try {
            $page = $this->driver()->getRows($table, $this->page($request), $this->perPage());
        } catch (UnknownTableException) {
            if ($this->wantsJson($request)) {
                return new JsonResponse(['message' => 'Unknown table.'], 404);
            }

            abort(404, 'Unknown table.');
        } catch (LaraDbException $e) {
            if ($this->wantsJson($request)) {
                return new JsonResponse(['message' => $e->getMessage()], 500);
            }

            return $this->viewResponse('laradb::partials.error', ['error' => $e->getMessage()], 500);
        }

        if ($this->wantsJson($request)) {
            return new JsonResponse($page->toArray());
        }

        return $this->view('laradb::partials.table', [
            'page' => $page,
            'selected' => $table,
        ]);
    }

    /**
     * @throws LaraDbException
     */
    private function driver(): DriverInterface
    {
        try {
            /** @var DriverInterface */
            return $this->container->make(DriverInterface::class);
        } catch (Throwable $e) {
            if ($e instanceof LaraDbException) {
                throw $e;
            }

            // Opening the connection failed: wrap it so the UI gets a message
            // it can show without quoting the DSN back at the visitor.
            throw ConnectionFailedException::forConnection($this->connectionName(), $e);
        }
    }

    /**
     * The database description, with the name shortened for display: a driver
     * reports the raw file path, and that is not something to put in a page.
     */
    private function describe(DriverInterface $driver): DatabaseInfo
    {
        $info = $driver->describe();

        $basePath = $this->container->bound('path.base') ? $this->container->make('path.base') : null;

        return new DatabaseInfo(
            engine: $info->engine,
            version: $info->version,
            name: DatabasePath::shorten($info->name, is_string($basePath) ? $basePath : null),
            tableCount: $info->tableCount,
            indexCount: $info->indexCount,
            sizeInBytes: $info->sizeInBytes,
            metadata: $info->metadata,
        );
    }

    private function connectionName(): string
    {
        $connection = $this->config->get('laradb.connection');

        return is_string($connection) && $connection !== ''
            ? $connection
            : (string) $this->config->get('database.default', 'default');
    }

    /**
     * The table asked for in the query string, validated against the schema
     * whitelist, falling back to the first table of the database.
     *
     * @param  list<TableInfo>  $tables
     */
    private function resolveSelectedTable(Request $request, array $tables): ?string
    {
        if ($tables === []) {
            return null;
        }

        $requested = $request->query('table');

        if (! is_string($requested) || $requested === '') {
            return $tables[0]->name;
        }

        foreach ($tables as $table) {
            if ($table->name === $requested) {
                return $requested;
            }
        }

        abort(404, 'Unknown table.');
    }

    private function page(Request $request): int
    {
        $page = $request->query('page');

        return is_numeric($page) ? max(1, (int) $page) : 1;
    }

    private function perPage(): int
    {
        return max(1, (int) $this->config->get('laradb.per_page', 25));
    }

    private function wantsJson(Request $request): bool
    {
        return $request->query('format') === 'json' || $request->wantsJson();
    }

    private function connectionError(LaraDbException $e): Response
    {
        // The visitor gets the sanitised message; the developer gets the real
        // one in the application log.
        report($e->getPrevious() ?? $e);

        return $this->viewResponse('laradb::index', [
            'tables' => [],
            'selected' => null,
            'page' => null,
            'error' => $e->getMessage(),
        ], 500);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function view(string $name, array $data): View
    {
        return $this->views->make($name, $this->viewData($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function viewResponse(string $name, array $data, int $status): Response
    {
        return new Response($this->view($name, $data), $status);
    }

    /**
     * Everything the views need on top of what the caller passed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function viewData(array $data): array
    {
        $data['presenter'] = new ValuePresenter(
            (int) $this->config->get('laradb.max_cell_length', 120),
        );
        $data['routePrefix'] = trim((string) $this->config->get('laradb.route_prefix', 'db'), '/');

        // The connection *name*, never its credentials.
        $data['connection'] = $this->connectionName();

        // The chrome is decoration: it renders with whatever it is given, and
        // an error page that never reached a driver is given nothing.
        $data['database'] ??= null;
        $data['queries'] ??= null;
        $data['runtime'] = Runtime::detect($this->container);

        return $data;
    }
}
