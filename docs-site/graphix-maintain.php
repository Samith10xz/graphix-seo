<?php
/*
| Graphix Docs housekeeping, run by cron every minute (no shell needed):
|  1. Fills APP_KEY in .env once, generated on the server (nobody ever sees it).
|  2. Once DB_PASSWORD is filled in, runs database migrations once per deployed release.
| Cheap no-op when there is nothing to do.
*/
chdir(__DIR__);
$env = @file_get_contents('.env');
if ($env === false) {
    exit(0);
}

if (preg_match('/^APP_KEY=\s*$/m', $env)) {
    $key = 'base64:'.base64_encode(random_bytes(32));
    $env = preg_replace('/^APP_KEY=\s*$/m', 'APP_KEY='.$key, $env);
    file_put_contents('.env', $env);
    echo "APP_KEY generated\n";
}

if (! preg_match('/^DB_PASSWORD=\S+/m', $env)) {
    echo "Waiting for DB_PASSWORD in .env\n";
    exit(0);
}

$release = trim((string) @file_get_contents('RELEASE'));
$marker = 'storage/app/graphix_deployed_release';
if ($release !== '' && trim((string) @file_get_contents($marker)) === $release) {
    exit(0);
}

passthru(escapeshellarg(PHP_BINARY).' artisan migrate --force --no-interaction', $code);
if ($code === 0) {
    passthru(escapeshellarg(PHP_BINARY).' artisan cache:clear --no-interaction');
    file_put_contents($marker, $release);
    echo "Migrated release {$release}\n";
}
