<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>LaraDb{{ $selected ? ' — '.$selected : '' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .laradb-scroll::-webkit-scrollbar { width: 10px; height: 10px; }
        .laradb-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; border: 3px solid transparent; background-clip: content-box; }
        .laradb-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; background-clip: content-box; }
    </style>
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased">
@php
    $base = url($routePrefix);
@endphp

<div class="flex h-full flex-col" x-data="laradb(@js($base), @js($selected))">

    {{-- Header --}}
    <header class="flex shrink-0 items-center justify-between gap-4 border-b border-slate-200 bg-white px-5 py-3">
        <div class="flex items-baseline gap-3">
            <a href="{{ $base }}" class="text-base font-semibold tracking-tight text-slate-900">LaraDb</a>
            <span class="text-xs text-slate-400">{{ $connection }}</span>
        </div>
        <div class="flex items-center gap-3">
            <span x-cloak x-show="loading" class="text-xs text-slate-400">Loading…</span>
            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                Read only
            </span>
        </div>
    </header>

    <div class="flex min-h-0 flex-1">

        {{-- Sidebar: the tables --}}
        <aside class="laradb-scroll flex w-72 shrink-0 flex-col overflow-y-auto border-r border-slate-200 bg-white">
            <div class="sticky top-0 z-10 border-b border-slate-100 bg-white/95 px-4 py-3 backdrop-blur">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                    Tables <span class="ml-1 font-normal normal-case tracking-normal text-slate-300">{{ count($tables) }}</span>
                </h2>
            </div>

            @if (count($tables) === 0)
                <p class="px-4 py-6 text-sm text-slate-400">No tables in this database.</p>
            @else
                <nav class="flex flex-col p-2">
                    @foreach ($tables as $table)
                        <a href="{{ $base }}?table={{ urlencode($table->name) }}"
                           x-on:click.prevent="select(@js($table->name))"
                           :class="selected === @js($table->name)
                                ? 'bg-slate-900 text-white'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                           class="group flex items-center justify-between gap-2 rounded-md px-3 py-1.5 text-sm transition">
                            <span class="truncate font-medium">{{ $table->name }}</span>
                            @if ($table->approximateRowCount !== null)
                                <span class="shrink-0 text-xs tabular-nums text-slate-400"
                                      title="{{ number_format($table->approximateRowCount) }} rows (estimate)">
                                    {{ number_format($table->approximateRowCount) }}
                                </span>
                            @endif
                        </a>
                    @endforeach
                </nav>
            @endif
        </aside>

        {{-- Main pane: the rows --}}
        <main class="flex min-w-0 flex-1 flex-col">
            @if ($error !== null)
                @include('laradb::partials.error', ['error' => $error])
            @elseif ($page === null)
                <div class="flex flex-1 items-center justify-center p-10">
                    <div class="text-center">
                        <p class="text-sm font-medium text-slate-500">Nothing to show yet</p>
                        <p class="mt-1 text-sm text-slate-400">This database does not contain any table.</p>
                    </div>
                </div>
            @else
                <div id="laradb-pane"
                     class="laradb-scroll flex min-h-0 flex-1 flex-col overflow-y-auto"
                     x-on:click="onPaneClick($event)">
                    @include('laradb::partials.table', ['page' => $page, 'selected' => $selected])
                </div>
            @endif
        </main>
    </div>
</div>

<script>
    function laradb(base, initial) {
        return {
            base: base,
            selected: initial,
            loading: false,

            init() {
                // The fragments we inject are plain HTML, so a browser "back"
                // has nothing to restore: reload rather than show stale rows.
                window.addEventListener('popstate', () => window.location.reload());
            },

            select(table) {
                if (table === this.selected) {
                    return;
                }
                this.selected = table;
                this.load(this.url(table, 1), table + ' — LaraDb');
                history.pushState({}, '', this.base + '?table=' + encodeURIComponent(table));
            },

            onPaneClick(event) {
                const link = event.target.closest('[data-laradb-page]');
                if (!link) {
                    return;
                }
                event.preventDefault();
                const page = parseInt(link.getAttribute('data-laradb-page'), 10);
                this.load(this.url(this.selected, page));
                history.replaceState({}, '', this.base + '?table=' + encodeURIComponent(this.selected) + '&page=' + page);
            },

            url(table, page) {
                return this.base + '/tables/' + encodeURIComponent(table) + '?page=' + page;
            },

            async load(url, title) {
                const pane = document.getElementById('laradb-pane');
                this.loading = true;
                pane.setAttribute('aria-busy', 'true');

                try {
                    const response = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                        credentials: 'same-origin',
                    });
                    pane.innerHTML = await response.text();
                    pane.scrollTop = 0;
                    if (title) {
                        document.title = title;
                    }
                } catch (error) {
                    pane.innerHTML = '<div class="flex flex-1 items-center justify-center p-10 text-sm text-rose-600"></div>';
                    pane.firstChild.textContent = 'Could not load this table: ' + error;
                } finally {
                    this.loading = false;
                    pane.removeAttribute('aria-busy');
                }
            },
        };
    }
</script>
</body>
</html>
