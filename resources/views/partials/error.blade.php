<div class="flex flex-1 items-center justify-center p-10">
    <div class="max-w-xl rounded-lg border border-rose-200 bg-rose-50 p-5">
        <h2 class="text-sm font-semibold text-rose-900">The database could not be read</h2>
        <p class="mt-2 break-words text-sm text-rose-700">{{ $error }}</p>
        <p class="mt-3 text-xs text-rose-600/80">
            Check the <code class="font-mono">laradb.connection</code> setting and that the configured
            connection is reachable.
        </p>
    </div>
</div>
