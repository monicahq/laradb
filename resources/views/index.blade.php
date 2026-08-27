<!DOCTYPE html>
<html lang="en" class="ldb-html">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>LaraDb{{ $selected ? ' — '.$selected : '' }}</title>

    {{-- The only remote request the viewer makes, and it is optional: the
         monospace stack below falls back to the system font when it fails,
         which is what happens when you open this on a plane. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap">

    <style>
        :root {
            --ldb-bg: #f4f3f0;
            --ldb-paper: #fcfcfb;
            --ldb-white: #fff;
            --ldb-sunk: #faf9f7;
            --ldb-strip: #f4f3ef;
            --ldb-line: #e0dfd9;
            --ldb-line-soft: #ecebe4;
            --ldb-line-faint: #f0eee7;
            --ldb-ink: #17171a;
            --ldb-ink-soft: #2c2b28;
            --ldb-dim: #6b6963;
            --ldb-mute: #8d8b84;
            --ldb-faint: #a8a6a0;
            --ldb-ghost: #c3c1ba;
            --ldb-accent: #0d7c8c;
            --ldb-accent-dark: #0a5f6b;
            --ldb-accent-wash: #f1f7f8;
            --ldb-accent-line: #dfeaec;
            --ldb-mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            --ldb-sans: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }

        *, *::before, *::after { box-sizing: border-box; }

        .ldb-html, body { height: 100%; }

        body {
            margin: 0;
            background: var(--ldb-bg);
            color: var(--ldb-ink);
            font-family: var(--ldb-sans);
            font-size: 13px;
            -webkit-font-smoothing: antialiased;
        }

        a { color: var(--ldb-accent); text-decoration: none; }
        a:hover { color: var(--ldb-accent-dark); }

        .ldb-scroll::-webkit-scrollbar { width: 10px; height: 10px; }
        .ldb-scroll::-webkit-scrollbar-track { background: var(--ldb-bg); }
        .ldb-scroll::-webkit-scrollbar-thumb {
            background: #d3d1ca;
            border: 2px solid var(--ldb-bg);
            border-radius: 6px;
        }
        .ldb-scroll::-webkit-scrollbar-thumb:hover { background: #b9b7ae; }

        .ldb-mono { font-family: var(--ldb-mono); }
        .ldb-num { font-variant-numeric: tabular-nums; }

        /* ---------------------------------------------------------------- */
        /* Shell                                                            */
        /* ---------------------------------------------------------------- */

        .ldb {
            display: flex;
            flex-direction: column;
            height: 100%;
            background: var(--ldb-paper);
        }

        .ldb-body { display: flex; flex: 1; min-height: 0; }

        /* ---------------------------------------------------------------- */
        /* Header                                                           */
        /* ---------------------------------------------------------------- */

        .ldb-header {
            flex: none;
            display: flex;
            align-items: stretch;
            height: 60px;
            background: var(--ldb-white);
            border-bottom: 1px solid var(--ldb-line);
        }

        .ldb-brand {
            flex: none;
            width: 248px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 16px;
            border-right: 1px solid var(--ldb-line);
        }
        .ldb-brand__name {
            display: block;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: -0.2px;
            color: var(--ldb-ink);
        }
        .ldb-brand__version {
            display: block;
            margin-top: 1px;
            font-family: var(--ldb-mono);
            font-size: 9.5px;
            letter-spacing: 0.06em;
            color: var(--ldb-mute);
        }

        .ldb-headerbar {
            flex: 1;
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 0 16px;
        }

        .ldb-engine {
            flex: none;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 10px 5px 8px;
            border: 1px solid #d9ded9;
            border-radius: 5px;
            background: #f7faf8;
            font-family: var(--ldb-mono);
            font-size: 11.5px;
        }
        .ldb-engine__name { font-weight: 600; color: var(--ldb-accent); }
        .ldb-engine__version { color: #5f8d94; }

        .ldb-dbname {
            font-family: var(--ldb-mono);
            font-size: 11.5px;
            color: var(--ldb-dim);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ldb-spacer { flex: 1; }

        .ldb-facts {
            display: flex;
            align-items: center;
            font-family: var(--ldb-mono);
            font-size: 10.5px;
            color: var(--ldb-mute);
            white-space: nowrap;
        }
        .ldb-facts > * {
            display: flex;
            align-items: baseline;
            gap: 5px;
            padding: 0 11px;
            border-left: 1px solid var(--ldb-line-soft);
        }
        .ldb-facts dt, .ldb-facts .ldb-k { color: var(--ldb-faint); letter-spacing: 0.04em; }
        .ldb-facts dd, .ldb-facts .ldb-v { margin: 0; color: #3f3e3a; font-weight: 500; }

        .ldb-readonly {
            flex: none;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-left: 12px;
            padding: 4px 9px;
            border: 1px solid #cfe3d4;
            border-radius: 99px;
            background: #f1f8f2;
            font-family: var(--ldb-mono);
            font-size: 10.5px;
            font-weight: 600;
            color: #2b6c45;
        }
        .ldb-readonly::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 99px;
            background: #2f9e5c;
        }

        /* The one moving part in the chrome: a table swap is fetched, so say so. */
        body.is-loading .ldb-readonly { opacity: 0.45; }

        /* ---------------------------------------------------------------- */
        /* Sidebar                                                          */
        /* ---------------------------------------------------------------- */

        .ldb-sidebar {
            flex: none;
            width: 248px;
            min-height: 0;
            display: flex;
            flex-direction: column;
            background: #fbfaf8;
            border-right: 1px solid var(--ldb-line);
        }

        .ldb-sidebar__title {
            flex: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 12px 9px 14px;
            background: var(--ldb-strip);
            border-bottom: 1px solid var(--ldb-line);
            font-family: var(--ldb-mono);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.12em;
            color: var(--ldb-dim);
        }
        .ldb-sidebar__title span { font-weight: 400; letter-spacing: 0; color: var(--ldb-faint); }

        .ldb-tablehead, .ldb-table {
            display: grid;
            grid-template-columns: 34px 1fr 62px;
            align-items: center;
        }

        .ldb-tablehead {
            flex: none;
            background: #faf9f6;
            border-bottom: 1px solid var(--ldb-line);
            font-family: var(--ldb-mono);
            font-size: 9px;
            letter-spacing: 0.1em;
            color: #b0aea8;
        }
        .ldb-tablehead span { padding: 5px 8px; border-right: 1px solid var(--ldb-line-soft); }
        .ldb-tablehead span:first-child { padding-left: 8px; }
        .ldb-tablehead span:last-child { border-right: 0; text-align: right; }

        .ldb-tables { flex: 1; min-height: 0; overflow-y: auto; }

        .ldb-table {
            background: var(--ldb-white);
            border-bottom: 1px solid #eeece6;
            font-family: var(--ldb-mono);
            line-height: 29px;
            cursor: pointer;
        }
        .ldb-table:hover { background: #f5f8f8; }
        .ldb-table__n {
            padding-left: 8px;
            font-size: 9.5px;
            color: var(--ldb-ghost);
            border-right: 1px solid #f2f0ea;
        }
        .ldb-table__name {
            padding: 0 8px;
            font-size: 11.5px;
            color: var(--ldb-ink-soft);
            border-right: 1px solid #f2f0ea;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .ldb-table__rows {
            padding: 0 8px;
            text-align: right;
            font-size: 10.5px;
            color: var(--ldb-faint);
            font-variant-numeric: tabular-nums;
        }

        .ldb-table.is-selected {
            background: var(--ldb-accent-wash);
            border-bottom-color: var(--ldb-line);
            box-shadow: inset 2px 0 0 var(--ldb-accent);
        }
        .ldb-table.is-selected .ldb-table__n { color: #7fb0b8; border-right-color: var(--ldb-accent-line); }
        .ldb-table.is-selected .ldb-table__name {
            font-weight: 600;
            color: var(--ldb-accent);
            border-right-color: var(--ldb-accent-line);
        }
        .ldb-table.is-selected .ldb-table__rows { color: var(--ldb-accent); }

        .ldb-sidebar__foot {
            flex: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 7px 12px;
            background: var(--ldb-strip);
            border-top: 1px solid var(--ldb-line);
            font-family: var(--ldb-mono);
            font-size: 9.5px;
            color: var(--ldb-mute);
        }

        .ldb-sidebar__empty { flex: 1; margin: 0; padding: 18px 14px; font-size: 12px; color: var(--ldb-faint); }

        /* ---------------------------------------------------------------- */
        /* Main pane                                                        */
        /* ---------------------------------------------------------------- */

        .ldb-main {
            flex: 1;
            min-width: 0;
            min-height: 0;
            display: flex;
            flex-direction: column;
            background: var(--ldb-white);
        }

        .ldb-pane {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }

        .ldb-status {
            flex: none;
            display: flex;
            align-items: center;
            height: 30px;
            background: var(--ldb-strip);
            border-top: 1px solid var(--ldb-line);
            font-family: var(--ldb-mono);
            font-size: 10px;
            color: var(--ldb-mute);
            overflow: hidden;
        }
        .ldb-status > * {
            display: flex;
            align-items: baseline;
            gap: 5px;
            padding: 0 12px;
            border-right: 1px solid #e6e4de;
            line-height: 29px;
            white-space: nowrap;
        }
        .ldb-status dt, .ldb-status .ldb-k { color: #aeaca6; }
        .ldb-status dd, .ldb-status .ldb-v { margin: 0; color: #3f3e3a; font-weight: 500; }
        .ldb-status__sig { margin-left: auto; border-right: 0; color: #aeaca6; padding: 0 14px; }

        /* ---------------------------------------------------------------- */
        /* The fragment: table strip, grid, pager                           */
        /* ---------------------------------------------------------------- */

        .ldb-strip {
            flex: none;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            background: #fdfdfc;
            border-bottom: 1px solid var(--ldb-line);
            font-family: var(--ldb-mono);
        }
        .ldb-strip__name {
            margin: 0;
            font-size: 13.5px;
            font-weight: 700;
            letter-spacing: -0.1px;
            color: var(--ldb-ink);
        }
        .ldb-strip__kind { font-size: 10.5px; color: var(--ldb-faint); }
        .ldb-strip__rule { flex: none; width: 1px; height: 16px; background: #e6e4de; }
        .ldb-strip__meta { font-size: 10.5px; color: var(--ldb-dim); white-space: nowrap; }
        .ldb-strip__sql {
            min-width: 0;
            font-size: 10.5px;
            color: var(--ldb-mute);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .ldb-strip__ms { font-size: 10.5px; color: var(--ldb-accent); white-space: nowrap; }
        .ldb-strip__range { font-size: 10.5px; color: var(--ldb-faint); white-space: nowrap; }

        .ldb-gridwrap {
            flex: 1;
            min-height: 0;
            overflow: auto;
            background: var(--ldb-sunk);
        }

        .ldb-grid {
            /* Narrow tables stretch to the pane; wide ones keep their natural
               width and scroll. The slack goes to the filler column so the
               real ones stay sized to their content. */
            min-width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-family: var(--ldb-mono);
            font-variant-numeric: tabular-nums;
        }

        .ldb-grid th {
            position: sticky;
            top: 0;
            z-index: 2;
            padding: 6px 9px;
            background: var(--ldb-strip);
            border-right: 1px solid #e4e2db;
            border-bottom: 1px solid #d8d6ce;
            text-align: left;
            vertical-align: top;
            white-space: nowrap;
            font-weight: 400;
        }
        .ldb-grid__col { display: flex; align-items: center; gap: 5px; }
        .ldb-grid__colname { font-size: 11.5px; font-weight: 600; color: #26251f; }
        .ldb-grid__type {
            display: block;
            margin-top: 2px;
            max-width: 260px;
            font-size: 9.5px;
            color: #a19f98;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ldb-badge {
            flex: none;
            padding: 1px 3px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.06em;
        }
        .ldb-badge--pk { color: #8a5b06; background: #fdf1d8; border: 1px solid #f0dcae; }
        .ldb-badge--fk { color: #3b5f8a; background: #e9f1fb; border: 1px solid #cfe0f4; }

        .ldb-grid td {
            max-width: 320px;
            padding: 0 9px;
            line-height: 30px;
            background: var(--ldb-white);
            border-right: 1px solid var(--ldb-line-faint);
            border-bottom: 1px solid #ecebe4;
            font-size: 11.5px;
            color: var(--ldb-ink-soft);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .ldb-grid tbody tr:hover td { background: #f5f9fa; }

        /* The row-number gutter stays put when the grid scrolls sideways:
           losing your place in a 40-column table is the whole problem. */
        .ldb-grid__gutter {
            position: sticky;
            left: 0;
            z-index: 1;
            width: 52px;
            min-width: 52px;
            padding: 0 8px;
            background: #faf9f6;
            font-size: 9.5px;
            color: var(--ldb-ghost);
            text-align: left;
        }
        .ldb-grid thead .ldb-grid__gutter { z-index: 3; font-size: 9px; color: #b0aea8; }
        .ldb-grid tbody tr:hover .ldb-grid__gutter { background: #f2f6f6; }

        .ldb-grid__filler { width: 100%; padding: 0; border-right: 0; }

        /* A foreign key you can follow. Underlined on hover rather than
           always, so a column of them does not turn the grid into a rug. */
        .ldb-ref { color: var(--ldb-accent); text-decoration: none; }
        .ldb-ref:hover { color: var(--ldb-accent-dark); text-decoration: underline; }
        .ldb-ref::after {
            content: '↗';
            margin-left: 4px;
            font-size: 9px;
            opacity: 0.5;
        }

        /* The filter chip: same wash and hairline as the selected sidebar row,
           so "you are looking at a subset" reads the same in both places. */
        .ldb-chip {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 2px 4px 2px 8px;
            border: 1px solid var(--ldb-accent-line);
            border-radius: 3px;
            background: var(--ldb-accent-wash);
            font-size: 10.5px;
            white-space: nowrap;
        }
        .ldb-chip__from { color: #5f8d94; }
        .ldb-chip__test { color: var(--ldb-accent); font-weight: 600; }
        .ldb-chip__clear {
            padding: 0 4px;
            border-radius: 2px;
            color: #7fb0b8;
            font-size: 10px;
            line-height: 16px;
        }
        .ldb-chip__clear:hover { background: var(--ldb-accent); color: #fff; text-decoration: none; }

        .ldb-null { font-size: 10px; letter-spacing: 0.06em; color: #c6c4bd; }

        .ldb-gridend {
            position: sticky;
            left: 0;
            margin: 0;
            padding: 0 9px;
            line-height: 34px;
            background: var(--ldb-sunk);
            border-bottom: 1px solid #ecebe4;
            font-family: var(--ldb-mono);
            font-size: 10.5px;
            color: #b0aea8;
        }

        .ldb-pager {
            flex: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 6px 12px;
            background: #fdfdfc;
            border-top: 1px solid var(--ldb-line);
            font-family: var(--ldb-mono);
            font-size: 10.5px;
        }
        .ldb-pager__pages { display: flex; align-items: center; gap: 2px; }
        .ldb-pager__step, .ldb-pager__page {
            display: block;
            padding: 3px 8px;
            border: 1px solid transparent;
            border-radius: 3px;
            color: var(--ldb-dim);
        }
        .ldb-pager__step { border-color: #e4e2db; }
        .ldb-pager__step:hover, .ldb-pager__page:hover {
            background: var(--ldb-accent-wash);
            border-color: var(--ldb-accent-line);
            color: var(--ldb-accent);
            text-decoration: none;
        }
        .ldb-pager__step.is-disabled {
            pointer-events: none;
            border-color: #f0eee7;
            color: #cfcdc6;
        }
        .ldb-pager__page.is-current {
            background: var(--ldb-accent);
            border-color: var(--ldb-accent);
            color: #fff;
            font-weight: 600;
        }
        .ldb-pager__gap { padding: 0 2px; color: var(--ldb-ghost); }

        /* ---------------------------------------------------------------- */
        /* Empty and error states, shared with the fragment                 */
        /* ---------------------------------------------------------------- */

        .ldb-empty {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background: var(--ldb-sunk);
            text-align: center;
        }
        .ldb-empty__title { margin: 0; font-size: 13px; font-weight: 600; color: var(--ldb-dim); }
        .ldb-empty__hint { margin: 6px 0 0; font-size: 12px; color: var(--ldb-faint); }

        .ldb-error {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background: var(--ldb-sunk);
        }
        .ldb-error__box {
            max-width: 560px;
            padding: 18px 20px;
            border: 1px solid #e8cdcd;
            border-left: 3px solid #b4453f;
            border-radius: 4px;
            background: #fdf6f5;
        }
        .ldb-error__title {
            margin: 0;
            font-family: var(--ldb-mono);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #8f3733;
        }
        .ldb-error__message {
            margin: 10px 0 0;
            font-family: var(--ldb-mono);
            font-size: 12px;
            line-height: 1.55;
            color: #7c3b37;
            overflow-wrap: anywhere;
        }
        .ldb-error__hint { margin: 12px 0 0; font-size: 12px; line-height: 1.5; color: #9c6e6a; }
        .ldb-error__hint code { font-family: var(--ldb-mono); font-size: 11.5px; }
    </style>
</head>
<body>
<div class="ldb">

    {{-- Header: what you are looking at, and what the engine says about itself --}}
    <header class="ldb-header">
        <div class="ldb-brand">
            <a href="{{ $url->root() }}" style="display:flex;align-items:center;gap:10px;color:inherit">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                    <ellipse cx="9" cy="4.2" rx="6.4" ry="2.4" stroke="#0d7c8c" stroke-width="1.4"/>
                    <path d="M2.6 4.2v4.4c0 1.33 2.87 2.4 6.4 2.4s6.4-1.07 6.4-2.4V4.2" stroke="#0d7c8c" stroke-width="1.4"/>
                    <path d="M2.6 8.6v4.4c0 1.33 2.87 2.4 6.4 2.4s6.4-1.07 6.4-2.4V8.6" stroke="#0d7c8c" stroke-width="1.4" opacity="0.45"/>
                </svg>
                <span>
                    <span class="ldb-brand__name">LaraDb</span>
                    @if ($runtime->package)
                        <span class="ldb-brand__version">{{ $runtime->package }}</span>
                    @endif
                </span>
            </a>
        </div>

        <div class="ldb-headerbar">
            @if ($database !== null)
                <div class="ldb-engine">
                    <svg width="14" height="14" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                        <ellipse cx="9" cy="4.4" rx="6.2" ry="2.3" fill="#0d7c8c" opacity="0.9"/>
                        <path d="M2.8 4.4v8.6c0 1.27 2.78 2.3 6.2 2.3s6.2-1.03 6.2-2.3V4.4" fill="#0d7c8c" opacity="0.18"/>
                        <path d="M2.8 4.4v8.6c0 1.27 2.78 2.3 6.2 2.3s6.2-1.03 6.2-2.3V4.4" stroke="#0d7c8c" stroke-width="1.3"/>
                        <path d="M2.8 9.1c0 1.27 2.78 2.3 6.2 2.3s6.2-1.03 6.2-2.3" stroke="#0d7c8c" stroke-width="1.3" opacity="0.5"/>
                    </svg>
                    <span class="ldb-engine__name">{{ $database->engine }}</span>
                    @if ($database->version)
                        <span class="ldb-engine__version">{{ $database->version }}</span>
                    @endif
                </div>

                @if ($database->name)
                    <div class="ldb-dbname" title="{{ $database->name }}">{{ $database->name }}</div>
                @endif
            @endif

            <div class="ldb-spacer"></div>

            @if ($database !== null && $database->metadata !== [])
                <dl class="ldb-facts">
                    @foreach ($database->metadata as $key => $value)
                        <div><dt>{{ $key }}</dt><dd>{{ $value }}</dd></div>
                    @endforeach
                </dl>
            @endif

            <span class="ldb-readonly">Read only</span>
        </div>
    </header>

    <div class="ldb-body">

        {{-- Sidebar: the tables --}}
        <aside class="ldb-sidebar">
            <div class="ldb-sidebar__title">
                TABLES <span class="ldb-num">{{ number_format(count($tables)) }}</span>
            </div>

            @if (count($tables) === 0)
                <p class="ldb-sidebar__empty">No tables in this database.</p>
            @else
                <div class="ldb-tablehead" aria-hidden="true">
                    <span>#</span><span>NAME</span><span>ROWS</span>
                </div>

                <nav class="ldb-tables ldb-scroll" id="laradb-tables" aria-label="Tables">
                    @foreach ($tables as $table)
                        <a href="{{ $url->page($table->name) }}"
                           data-laradb-fragment="{{ $url->fragment($table->name) }}"
                           data-laradb-table="{{ $table->name }}"
                           @class(['ldb-table', 'is-selected' => $selected === $table->name])
                           @if ($selected === $table->name) aria-current="page" @endif>
                            <span class="ldb-table__n">{{ $loop->iteration }}</span>
                            <span class="ldb-table__name" title="{{ $table->name }}">{{ $table->name }}</span>
                            <span class="ldb-table__rows"
                                  @if ($table->approximateRowCount !== null)
                                      title="{{ number_format($table->approximateRowCount) }} rows (estimate)"
                                  @endif>
                                {{ $table->approximateRowCount === null ? '—' : number_format($table->approximateRowCount) }}
                            </span>
                        </a>
                    @endforeach
                </nav>
            @endif

            <div class="ldb-sidebar__foot">
                <span>
                    {{ number_format(count($tables)) }} {{ count($tables) === 1 ? 'table' : 'tables' }}
                    @if ($database?->indexCount !== null)
                        · {{ number_format($database->indexCount) }} {{ $database->indexCount === 1 ? 'index' : 'indexes' }}
                    @endif
                </span>
                <span>{{ $database?->formattedSize() ?? '' }}</span>
            </div>
        </aside>

        {{-- Main pane: the rows --}}
        <main class="ldb-main">
            <div id="laradb-pane" class="ldb-pane">
                @if ($error !== null)
                    @include('laradb::partials.error', ['error' => $error])
                @elseif ($page === null)
                    <div class="ldb-empty">
                        <div>
                            <p class="ldb-empty__title">Nothing to show yet</p>
                            <p class="ldb-empty__hint">This database does not contain any table.</p>
                        </div>
                    </div>
                @else
                    @include('laradb::partials.table', ['page' => $page, 'selected' => $selected])
                @endif
            </div>

            {{-- Status strip: facts about the connection, not about this page,
                 so it lives outside the fragment the JS swaps. --}}
            <dl class="ldb-status">
                <div><dt>conn</dt><dd>{{ $connection }}</dd></div>
                @if ($database !== null)
                    <div><dt>engine</dt><dd>{{ $database->engine }}{{ $database->version ? ' '.$database->version : '' }}</dd></div>
                    @if ($database->formattedSize() !== null)
                        <div><dt>size</dt><dd>{{ $database->formattedSize() }}</dd></div>
                    @endif
                @endif
                @if ($queries !== null)
                    <div><dt>queries</dt><dd class="ldb-num">{{ $queries }}</dd></div>
                @endif
                <span class="ldb-status__sig">laradb{{ $runtime->package ? ' '.$runtime->package : '' }} · php {{ $runtime->php }}@if ($runtime->laravel) · laravel {{ $runtime->laravel }} @endif</span>
            </dl>
        </main>
    </div>
</div>

<script>
    (function () {
        var pane = document.getElementById('laradb-pane');
        var tables = document.getElementById('laradb-tables');

        // Every navigable link on the page — a table in the sidebar, a page in
        // the pager, a foreign key in a cell, the ✕ on a filter — carries the
        // fragment URL to fetch next to the href a browser would follow. That
        // keeps URL building on the server, where the filter already lives, and
        // leaves this file with one code path instead of one per kind of link.
        function markSelected(table) {
            if (!tables || !table) {
                return;
            }

            tables.querySelectorAll('[data-laradb-table]').forEach(function (link) {
                var current = link.getAttribute('data-laradb-table') === table;
                link.classList.toggle('is-selected', current);
                if (current) {
                    link.setAttribute('aria-current', 'page');
                } else {
                    link.removeAttribute('aria-current');
                }
            });
        }

        function load(fragment, title) {
            document.body.classList.add('is-loading');
            pane.setAttribute('aria-busy', 'true');

            return fetch(fragment, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                credentials: 'same-origin',
            }).then(function (response) {
                return response.text();
            }).then(function (html) {
                pane.innerHTML = html;
                pane.scrollTop = 0;
                if (title) {
                    document.title = title;
                }
            }).catch(function (error) {
                pane.textContent = '';
                var box = document.createElement('div');
                box.className = 'ldb-empty';
                box.textContent = 'Could not load this table: ' + error;
                pane.appendChild(box);
            }).finally(function () {
                document.body.classList.remove('is-loading');
                pane.removeAttribute('aria-busy');
            });
        }

        document.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey ||
                event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            var link = event.target.closest('[data-laradb-fragment]');
            if (!link) {
                return;
            }

            event.preventDefault();

            var table = link.getAttribute('data-laradb-table');

            // No early return when the table is already selected: clicking the
            // table you are on is how you drop a filter.
            markSelected(table);
            load(link.getAttribute('data-laradb-fragment'), table ? table + ' — LaraDb' : null);
            history.pushState({}, '', link.getAttribute('href'));
        });

        // The fragments we inject are plain HTML, so a browser "back" has
        // nothing to restore: reload rather than show stale rows.
        window.addEventListener('popstate', function () {
            window.location.reload();
        });
    })();
</script>
</body>
</html>
