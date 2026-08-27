@php
    $primaryKey = $page->primaryKey();
    $filter = $page->filter;
@endphp

{{-- What this table is, and what it cost to read --}}
<div class="ldb-strip">
    <h1 class="ldb-strip__name">{{ $page->table }}</h1>
    <span class="ldb-strip__kind">TABLE</span>
    <span class="ldb-strip__rule"></span>
    <span class="ldb-strip__meta">
        {{ number_format(count($page->columns)) }} {{ count($page->columns) === 1 ? 'column' : 'columns' }}
        · {{ number_format($page->total) }} {{ $page->total === 1 ? 'row' : 'rows' }}
        @if ($primaryKey !== null)
            · pk {{ $primaryKey }}
        @endif
    </span>

    @if ($filter !== null)
        <span class="ldb-chip">
            @if ($from !== null)
                <span class="ldb-chip__from">← {{ $from }}</span>
            @endif
            <span class="ldb-chip__test">{{ $filter->column }} = {{ $filter->value }}</span>
            <a class="ldb-chip__clear"
               href="{{ $url->page($page->table) }}"
               data-laradb-fragment="{{ $url->fragment($page->table) }}"
               data-laradb-table="{{ $page->table }}"
               title="Show the whole table"
               aria-label="Clear the filter">✕</a>
        </span>
    @endif

    <span class="ldb-spacer"></span>

    @if ($page->sql !== '')
        <span class="ldb-strip__sql" title="{{ $page->sql }}">{{ $page->sql }}</span>
        <span class="ldb-strip__rule"></span>
    @endif
    <span class="ldb-strip__ms ldb-num">{{ number_format($page->durationMs, 2) }} ms</span>
    <span class="ldb-strip__range ldb-num">
        @if ($page->total === 0)
            no rows
        @else
            {{ number_format((int) $page->from()) }}–{{ number_format((int) $page->to()) }} of {{ number_format($page->total) }}
        @endif
    </span>
</div>

@if ($page->isEmpty())
    <div class="ldb-empty">
        <div>
            @if ($filter !== null)
                {{-- A filter that matches nothing is a different story from an
                     empty table, and the way out of it is different too. --}}
                <p class="ldb-empty__title">No row matches</p>
                <p class="ldb-empty__hint">
                    Nothing in <span class="ldb-mono">{{ $page->table }}</span> has
                    <span class="ldb-mono">{{ $filter->column }} = {{ $filter->value }}</span>.
                </p>
                <p class="ldb-empty__hint">
                    <a href="{{ $url->page($page->table) }}"
                       data-laradb-fragment="{{ $url->fragment($page->table) }}"
                       data-laradb-table="{{ $page->table }}">Show the whole table</a>
                </p>
            @else
                <p class="ldb-empty__title">This table is empty</p>
                <p class="ldb-empty__hint">
                    <span class="ldb-mono">{{ $page->table }}</span>
                    has {{ number_format(count($page->columns)) }} {{ count($page->columns) === 1 ? 'column' : 'columns' }}
                    and no rows.
                </p>
            @endif
        </div>
    </div>
@else
    <div class="ldb-gridwrap ldb-scroll">
        <table class="ldb-grid">
            <thead>
                <tr>
                    <th scope="col" class="ldb-grid__gutter" aria-label="Row number">#</th>
                    @foreach ($page->columns as $column)
                        <th scope="col">
                            <span class="ldb-grid__col">
                                <span class="ldb-grid__colname">{{ $column->name }}</span>
                                @if ($column->primaryKey)
                                    <span class="ldb-badge ldb-badge--pk" title="Primary key">PK</span>
                                @endif
                                @if ($column->foreignKey !== null)
                                    <span class="ldb-badge ldb-badge--fk"
                                          title="References {{ $column->foreignKey }}">FK</span>
                                @endif
                            </span>
                            <span class="ldb-grid__type"
                                  title="{{ $column->type }}{{ $column->nullable ? ', nullable' : ', not null' }}">
                                {{ $column->type }}
                            </span>
                        </th>
                    @endforeach
                    <th class="ldb-grid__filler" aria-hidden="true"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($page->rows as $row)
                    <tr>
                        <td class="ldb-grid__gutter ldb-num">{{ number_format((int) $page->from() + $loop->index) }}</td>
                        @foreach ($page->columns as $column)
                            @php $value = $row[$column->name] ?? null; @endphp
                            <td @if ($presenter->isTruncated($value)) title="{{ $presenter->full($value) }}" @endif>
                                @if ($presenter->isNull($value))
                                    <span class="ldb-null">NULL</span>
                                @elseif ($column->foreignKey !== null)
                                    @php
                                        [$targetTable, $targetColumn] = explode('.', $column->foreignKey, 2);
                                        $reference = new \LaraDb\DTO\RowFilter($targetColumn, $presenter->full($value));
                                        $origin = $page->table.'.'.$column->name;
                                    @endphp
                                    <a class="ldb-ref"
                                       href="{{ $url->page($targetTable, 1, $reference, $origin) }}"
                                       data-laradb-fragment="{{ $url->fragment($targetTable, 1, $reference, $origin) }}"
                                       data-laradb-table="{{ $targetTable }}"
                                       title="Go to {{ $column->foreignKey }} = {{ $presenter->full($value) }}">{{ $presenter->display($value) }}</a>
                                @else
                                    {{ $presenter->display($value) }}
                                @endif
                            </td>
                        @endforeach
                        <td class="ldb-grid__filler"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="ldb-gridend">
            end of page — {{ number_format((int) $page->from()) }}–{{ number_format((int) $page->to()) }}
            of {{ number_format($page->total) }} {{ $page->total === 1 ? 'row' : 'rows' }}
            @if ($page->lastPage() > 1)
                · page {{ number_format($page->page) }} of {{ number_format($page->lastPage()) }}
            @endif
        </p>
    </div>

    {{-- Pagination --}}
    @if ($page->lastPage() > 1)
        <nav class="ldb-pager" aria-label="Pagination">
            <a href="{{ $url->page($page->table, $page->page - 1, $filter, $from) }}"
               @if ($page->hasPreviousPage())
                   data-laradb-fragment="{{ $url->fragment($page->table, $page->page - 1, $filter, $from) }}"
                   data-laradb-table="{{ $page->table }}"
               @endif
               @class(['ldb-pager__step', 'is-disabled' => ! $page->hasPreviousPage()])
               @if (! $page->hasPreviousPage()) aria-disabled="true" tabindex="-1" @endif>
                ← prev
            </a>

            <div class="ldb-pager__pages">
                @foreach ($page->paginationWindow() as $number)
                    @if ($number === null)
                        <span class="ldb-pager__gap">…</span>
                    @elseif ($number === $page->page)
                        <span aria-current="page" class="ldb-pager__page is-current ldb-num">{{ $number }}</span>
                    @else
                        <a href="{{ $url->page($page->table, $number, $filter, $from) }}"
                           data-laradb-fragment="{{ $url->fragment($page->table, $number, $filter, $from) }}"
                           data-laradb-table="{{ $page->table }}"
                           class="ldb-pager__page ldb-num">{{ $number }}</a>
                    @endif
                @endforeach
            </div>

            <a href="{{ $url->page($page->table, $page->page + 1, $filter, $from) }}"
               @if ($page->hasNextPage())
                   data-laradb-fragment="{{ $url->fragment($page->table, $page->page + 1, $filter, $from) }}"
                   data-laradb-table="{{ $page->table }}"
               @endif
               @class(['ldb-pager__step', 'is-disabled' => ! $page->hasNextPage()])
               @if (! $page->hasNextPage()) aria-disabled="true" tabindex="-1" @endif>
                next →
            </a>
        </nav>
    @endif
@endif
