<?php

/**
 * Kernel-level query-count harness for Laravel API endpoints.
 *
 * Usage:
 *   php verify_endpoint.php /path/to/laravel-project "/api/v2/pages/foo" "/api/v2/posts/type/news?load=5" ...
 *
 * Hits each path 3 times through the HTTP kernel (no server needed) and
 * prints status + query count per hit, plus repeated query shapes on the
 * last hit (repeats with count > 1 usually mean an N+1 or a double render).
 *
 * Expectation for a cached endpoint: hit1 > 0 (cold fill), hit2/hit3 = 0.
 */

if ($argc < 3) {
    fwrite(STDERR, "usage: php verify_endpoint.php <project-path> <path> [<path> ...]\n");
    exit(1);
}

$projectPath = rtrim($argv[1], '/');
$paths = array_slice($argv, 2);

require $projectPath . '/vendor/autoload.php';
$app = require $projectPath . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
// Bootstrap the app before touching facades/helpers like cache() or DB
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function hitOnce($kernel, string $path, ?string $locale = null): array
{
    Illuminate\Support\Facades\DB::enableQueryLog();
    Illuminate\Support\Facades\DB::flushQueryLog();

    $request = Illuminate\Http\Request::create($path, 'GET');
    if ($locale !== null) {
        $request->headers->set('X-Locale', $locale);
    }

    $response = $kernel->handle($request);
    $log = Illuminate\Support\Facades\DB::getQueryLog();

    return [$response->getStatusCode(), $log];
}

$locale = getenv('VERIFY_LOCALE') ?: null;

foreach ($paths as $path) {
    $results = [];
    $lastLog = [];
    for ($i = 1; $i <= 3; $i++) {
        [$status, $log] = hitOnce($kernel, $path, $locale);
        $results[] = sprintf('hit%d %d:%d', $i, $status, count($log));
        $lastLog = $log;
    }

    printf("%-55s %s\n", $path, implode('  ', $results));

    // repeated shapes on the final (should-be-warm) hit
    $shapes = [];
    foreach ($lastLog as $q) {
        $shape = substr(preg_replace("/\\d+|'[^']*'/", '?', $q['query']), 0, 110);
        $shapes[$shape] = ($shapes[$shape] ?? 0) + 1;
    }
    foreach ($shapes as $shape => $n) {
        if ($n > 1) {
            printf("    %dx %s\n", $n, $shape);
        }
    }
}
