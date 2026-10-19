<?php

/**
 * One-time maintenance page. DELETE THIS FILE AFTER USE.
 *
 * It is added to an update ZIP automatically when the update contains a new
 * database migration, because the live host gives no SSH and no scheduled
 * tasks: there is no other way to run `php artisan migrate` on it.
 *
 * Guards, in order of how much they matter:
 *
 *   1. The token is 64 random hex characters, baked in at build time and
 *      never written to the repository. Without it this returns 404, so the
 *      page is invisible to anyone who has not been told the address.
 *   2. It only ever runs a fixed list of commands. There is no parameter that
 *      can be used to run something else.
 *   3. It deletes itself as soon as the work succeeds, and says so if it
 *      could not (usually a file permission problem).
 *
 * __RUN_MIGRATIONS__ is true when the update carried migrations.
 */

const MAINTENANCE_TOKEN = '__TOKEN__';
const RUN_MIGRATIONS = __RUN_MIGRATIONS__;

header('Content-Type: text/plain; charset=utf-8');

if (!hash_equals(MAINTENANCE_TOKEN, (string) ($_GET['token'] ?? ''))) {
    http_response_code(404);
    exit("Not found\n");
}

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

$app = require_once $root . '/bootstrap/app.php';

/** @var Illuminate\Contracts\Console\Kernel $kernel */
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "TRUSTMED PHARMACY - update maintenance\n";
echo "======================================\n\n";

$failed = false;

/**
 * Runs one artisan command and prints everything it said.
 */
$run = function (string $command) use ($kernel, &$failed): void {
    echo "Running: php artisan {$command}\n";
    echo str_repeat('-', 60) . "\n";

    try {
        $exit = $kernel->call($command);
    } catch (Throwable $e) {
        echo 'FAILED: ' . $e->getMessage() . "\n\n";
        $failed = true;

        return;
    }

    echo $kernel->output();
    echo "Exit code: {$exit}\n\n";

    if ($exit !== 0) {
        $failed = true;
    }
};

// Migrations first: if one fails, the caches being stale is the least of the
// problems, but clearing them afterwards is harmless either way.
if (RUN_MIGRATIONS) {
    $run('migrate --force');
} else {
    echo "No migrations in this update.\n\n";
}

// Never `optimize`, only `optimize:clear`: the application is deliberately
// left uncached in production so the next update is a plain file copy.
$run('optimize:clear');

// The application's own health check: database reachable, key not the leaked
// one, storage writable, no cached files left behind.
$run('pmas:preflight');

// Prove the site still boots as a web request, not just as a console command.
echo "Checking the site answers as normal:\n";
echo str_repeat('-', 60) . "\n";
try {
    $response = $app->handle(
        Illuminate\Http\Request::create('/up', 'GET')
    );
    $status = $response->getStatusCode();
    echo "  GET /up -> {$status}\n\n";

    if ($status >= 400) {
        $failed = true;
        echo "The application did not answer its health check.\n\n";
    }
} catch (Throwable $e) {
    $failed = true;
    echo '  The application threw: ' . $e->getMessage() . "\n\n";
}

if ($failed) {
    echo "SOMETHING FAILED. This page has NOT deleted itself, so the command\n";
    echo "above can be run again once the cause is fixed.\n";
    exit(1);
}

echo "All done. Removing this page.\n";

if (@unlink(__FILE__)) {
    echo "It has deleted itself.\n";
} else {
    echo "WARNING: could not delete this file. Delete public/_maintenance.php by hand.\n";
}
