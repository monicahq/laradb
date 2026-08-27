<div class="ldb-error">
    <div class="ldb-error__box">
        <h2 class="ldb-error__title">The database could not be read</h2>
        <p class="ldb-error__message">{{ $error }}</p>
        <p class="ldb-error__hint">
            Check the <code>laradb.connection</code> setting and that the configured
            connection is reachable.
        </p>
    </div>
</div>
