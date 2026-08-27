@php
    $base = url($routePrefix);
    $pageUrl = static fn (int $number): string => $base.'?table='.urlencode($page->table).'&page='.$number;
@endphp

{{-- Table header strip --}}
<div class="flex shrink-0 flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-slate-200 bg-white px-5 py-3">
    <h1 class="font-mono text-sm font-semibold text-slate-900">{{ $page->table }}</h1>
    <p class="text-xs text-slate-400 tabular-nums">
        @if ($page->total === 0)
            no rows
        @else
            {{ number_format((int) $page->from()) }}–{{ number_format((int) $page->to()) }}
            of {{ number_format($page->total) }} {{ $page->total === 1 ? 'row' : 'rows' }}
            · {{ count($page->columns) }} {{ count($page->columns) === 1 ? 'column' : 'columns' }}
        @endif
    </p>
</div>

@if ($page->isEmpty())
    <div class="flex flex-1 items-center justify-center p-10">
        <div class="text-center">
            <p class="text-sm font-medium text-slate-500">This table is empty</p>
            <p class="mt-1 text-sm text-slate-400">
                <span class="font-mono">{{ $page->table }}</span> has {{ count($page->columns) }} columns and no rows.
            </p>
        </div>
    </div>
@else
    <div class="laradb-scroll min-h-0 flex-1 overflow-auto">
        <table class="min-w-full border-separate border-spacing-0 text-sm">
            <thead>
                <tr>
                    @foreach ($page->columns as $column)
                        <th scope="col"
                            class="sticky top-0 z-10 whitespace-nowrap border-b border-slate-200 bg-slate-50 px-4 py-2 text-left align-bottom">
                            <span class="flex items-baseline gap-1.5">
                                <span class="font-mono text-xs font-semibold text-slate-700">{{ $column->name }}</span>
                                @if ($column->primaryKey)
                                    <span class="rounded bg-amber-100 px-1 text-[10px] font-semibold uppercase tracking-wide text-amber-700"
                                          title="Primary key">pk</span>
                                @endif
                            </span>
                            <span class="mt-0.5 block truncate text-[11px] font-normal lowercase text-slate-400"
                                  title="{{ $column->type }}{{ $column->nullable ? ', nullable' : ', not null' }}">
                                {{ $column->type }}
                            </span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($page->rows as $row)
                    <tr class="even:bg-slate-50/60 hover:bg-sky-50/70">
                        @foreach ($page->columns as $column)
                            @php $value = $row[$column->name] ?? null; @endphp
                            <td class="max-w-md truncate border-b border-slate-100 px-4 py-1.5 align-top font-mono text-xs
                                       {{ $presenter->isNull($value) ? 'italic text-slate-300' : 'text-slate-700' }}"
                                @if ($presenter->isTruncated($value)) title="{{ $presenter->full($value) }}" @endif>
                                {{ $presenter->display($value) }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if ($page->lastPage() > 1)
        <nav class="flex shrink-0 items-center justify-between gap-4 border-t border-slate-200 bg-white px-5 py-2.5"
             aria-label="Pagination">
            <a href="{{ $pageUrl($page->page - 1) }}"
               @if ($page->hasPreviousPage()) data-laradb-page="{{ $page->page - 1 }}" @endif
               @class([
                   'rounded-md px-2.5 py-1 text-xs font-medium ring-1 ring-inset',
                   'text-slate-600 ring-slate-200 hover:bg-slate-50 hover:text-slate-900' => $page->hasPreviousPage(),
                   'pointer-events-none text-slate-300 ring-slate-100' => ! $page->hasPreviousPage(),
               ])
               @if (! $page->hasPreviousPage()) aria-disabled="true" tabindex="-1" @endif>
                ← Previous
            </a>

            <div class="flex items-center gap-1">
                @foreach ($page->paginationWindow() as $number)
                    @if ($number === null)
                        <span class="px-1 text-xs text-slate-300">…</span>
                    @elseif ($number === $page->page)
                        <span aria-current="page"
                              class="rounded-md bg-slate-900 px-2.5 py-1 text-xs font-semibold tabular-nums text-white">{{ $number }}</span>
                    @else
                        <a href="{{ $pageUrl($number) }}"
                           data-laradb-page="{{ $number }}"
                           class="rounded-md px-2.5 py-1 text-xs tabular-nums text-slate-600 hover:bg-slate-100 hover:text-slate-900">{{ $number }}</a>
                    @endif
                @endforeach
            </div>

            <a href="{{ $pageUrl($page->page + 1) }}"
               @if ($page->hasNextPage()) data-laradb-page="{{ $page->page + 1 }}" @endif
               @class([
                   'rounded-md px-2.5 py-1 text-xs font-medium ring-1 ring-inset',
                   'text-slate-600 ring-slate-200 hover:bg-slate-50 hover:text-slate-900' => $page->hasNextPage(),
                   'pointer-events-none text-slate-300 ring-slate-100' => ! $page->hasNextPage(),
               ])
               @if (! $page->hasNextPage()) aria-disabled="true" tabindex="-1" @endif>
                Next →
            </a>
        </nav>
    @endif
@endif
