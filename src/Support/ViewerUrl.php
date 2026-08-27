<?php

declare(strict_types=1);

namespace LaraDb\Support;

use LaraDb\DTO\RowFilter;

/**
 * Builds the viewer's own links.
 *
 * Every navigable thing on the page exists twice: as an href the browser can
 * follow on its own, and as the fragment endpoint the JavaScript fetches
 * instead. Keeping both in one place is what lets the page carry a filter
 * through pagination without the templates — or the JavaScript — having to
 * know how a viewer URL is spelled.
 */
final class ViewerUrl
{
    private readonly string $base;

    public function __construct(string $base)
    {
        // Deliberately reduced to a path. Laravel's url() answers with an
        // absolute URL built from APP_URL, and an APP_URL that does not match
        // the host you are actually browsing on would send every fetch to the
        // wrong origin and make pushState throw. A path cannot be wrong about
        // where it is.
        $path = parse_url($base, PHP_URL_PATH);

        $this->base = is_string($path) && $path !== '' ? rtrim($path, '/') : '';
    }

    /**
     * The full page, for the address bar and for visitors without JavaScript.
     */
    public function page(string $table, int $page = 1, ?RowFilter $filter = null, ?string $from = null): string
    {
        return $this->base.'?'.$this->query(['table' => $table] + $this->pageQuery($page, $filter, $from));
    }

    /**
     * The viewer's own root, with nothing selected.
     */
    public function root(): string
    {
        return $this->base === '' ? '/' : $this->base;
    }

    /**
     * The HTML fragment the page swaps into its main pane.
     */
    public function fragment(string $table, int $page = 1, ?RowFilter $filter = null, ?string $from = null): string
    {
        $query = $this->query($this->pageQuery($page, $filter, $from));

        return $this->base.'/tables/'.rawurlencode($table).($query === '' ? '' : '?'.$query);
    }

    /**
     * @return array<string, string>
     */
    private function pageQuery(int $page, ?RowFilter $filter, ?string $from): array
    {
        $query = [];

        if ($page > 1) {
            $query['page'] = (string) $page;
        }

        if ($filter !== null) {
            $query['column'] = $filter->column;
            $query['value'] = $filter->value;
        }

        // Only meaningful alongside a filter: it names the foreign key that
        // led here, and there is nothing to lead anywhere without one.
        if ($filter !== null && $from !== null && $from !== '') {
            $query['from'] = $from;
        }

        return $query;
    }

    /**
     * @param  array<string, string>  $query
     */
    private function query(array $query): string
    {
        return http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
