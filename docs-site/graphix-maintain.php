<?php
/*
| Graphix Docs housekeeping, run by cron every minute (no shell needed):
|  1. Fills APP_KEY in .env once, generated on the server (nobody ever sees it).
|  2. Once DB_PASSWORD is filled in, runs database migrations once per deployed release.
|  3. Right after the first migration, replaces BookStack's well-known default login
|     (admin@admin.com / password) with the owner account: random unusable password plus a
|     one-time link (valid 14 days) where the owner sets their own password.
| Cheap no-op when there is nothing to do.
*/
chdir(__DIR__);
ini_set('display_errors', 'stdout');
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

// Quote secret values that contain characters .env would misread (#, spaces, $, quotes).
// The value itself is never printed.
$env = preg_replace_callback('/^((?:DB|MAIL)_PASSWORD)=(.+)$/m', function ($m) {
    $v = rtrim($m[2]);
    if ($v === '' || $v[0] === "'" || $v[0] === '"' || ! preg_match('/[#\s"$\\\\`]/', $v)) {
        return $m[1].'='.$v;
    }
    if (strpos($v, "'") === false) {
        return $m[1]."='".$v."'";
    }
    return $m[1].'="'.addcslashes($v, '"\\$').'"';
}, $env, -1, $changed);
if ($env !== @file_get_contents('.env')) {
    file_put_contents('.env', $env);
    echo "Quoted a password value in .env\n";
}

if (! preg_match('/^DB_PASSWORD=\S+/m', $env)) {
    echo "Waiting for DB_PASSWORD in .env\n";
    exit(0);
}

$release = trim((string) @file_get_contents('RELEASE'));
$marker = 'storage/app/graphix_deployed_release';
if ($release !== '' && trim((string) @file_get_contents($marker)) === $release && file_exists('storage/app/graphix_owner_ready')) {
    exit(0); // nothing to do
}
// The release ships composer.json renamed (so Hostinger never runs Composer), but some
// artisan commands read it. Put it back only while this script runs.
$tempComposer = false;
if (! file_exists('composer.json') && file_exists('composer.build.json')) {
    copy('composer.build.json', 'composer.json');
    $tempComposer = true;
}
register_shutdown_function(function () use ($tempComposer) {
    if ($tempComposer) {
        @unlink(__DIR__.'/composer.json');
    }
});
$app = bootApp();
if ($release === '' || trim((string) @file_get_contents($marker)) !== $release) {
    // Hostinger disables exec()/passthru(), so artisan runs in-process.
    try {
        $code = Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        echo trim(Illuminate\Support\Facades\Artisan::output()), "\n";
        Illuminate\Support\Facades\Artisan::call('cache:clear');
    } catch (Throwable $e) {
        echo 'Migration failed: ', get_class($e), ': ', $e->getMessage(), "\n";
        exit(1);
    }
    if ($code !== 0) {
        echo "Migration failed (exit {$code})\n";
        exit(1);
    }
    file_put_contents($marker, $release);
    echo "Migrated release {$release}\n";
}

try {
    secureDefaultAdmin($env, $app);
} catch (Throwable $e) {
    echo 'Owner setup failed: ', get_class($e), ': ', $e->getMessage(), "\n";
    exit(1);
}

function bootApp()
{
    require_once __DIR__.'/vendor/autoload.php';
    $app = require __DIR__.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    return $app;
}

function secureDefaultAdmin(string $env, $app): void
{
    $marker = 'storage/app/graphix_owner_ready';
    if (file_exists($marker)) {
        return;
    }
    $user = BookStack\Users\Models\User::query()->where('email', 'admin@admin.com')->first();
    if (! $user) {
        file_put_contents($marker, date('c'));
        return;
    }
    $email = preg_match('/^GRAPHIX_OWNER_EMAIL=(\S+)/m', $env, $m) ? $m[1] : 'admin@graphixdesign.agency';
    $user->forceFill([
        'email' => $email,
        'name' => 'Graphix Owner',
        'password' => Illuminate\Support\Facades\Hash::make(Illuminate\Support\Str::random(64)),
    ])->save();

    $invites = $app->make(BookStack\Access\UserInviteService::class);
    $create = new ReflectionMethod($invites, 'createTokenForUser');
    $create->setAccessible(true);
    $token = $create->invoke($invites, $user);

    $url = rtrim((string) config('app.url'), '/').'/register/invite/'.$token;
    file_put_contents('storage/app/graphix-owner-link.txt', $url.PHP_EOL);
    file_put_contents($marker, date('c'));
    echo "Default admin replaced. Owner login: {$email}. One-time set-password link saved in storage/app/graphix-owner-link.txt\n";
}
