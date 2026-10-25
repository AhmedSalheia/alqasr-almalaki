<?php

/*
 * Builds the .env used for a release build in CI from .env.example + GitHub secrets.
 * The version comes from the git tag: v1.2.3 -> NATIVEPHP_APP_VERSION=1.2.3, VERSION_CODE=10203.
 */

$env = file_get_contents(__DIR__.'/../.env.example');

$tag = ltrim((string) getenv('RELEASE_TAG'), 'v');
if (! preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $tag, $m)) {
    fwrite(STDERR, "Release tag must look like v1.2.3, got '".getenv('RELEASE_TAG')."'\n");
    exit(1);
}
$versionCode = ((int) $m[1]) * 10000 + ((int) $m[2]) * 100 + (int) $m[3];

$values = [
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'APP_KEY' => getenv('APP_KEY'),
    'FIREBASE_API_KEY' => getenv('FIREBASE_API_KEY'),
    'FIREBASE_PROJECT_ID' => getenv('FIREBASE_PROJECT_ID'),
    'NATIVEPHP_APP_VERSION' => $tag,
    'NATIVEPHP_APP_VERSION_CODE' => (string) $versionCode,
    'QUEUE_CONNECTION' => 'database',
    // The app checks this repo's GitHub Releases for updates
    'ALQASR_UPDATE_REPO' => getenv('GITHUB_REPOSITORY'),
];

foreach ($values as $key => $value) {
    if ($value === false || $value === '') {
        fwrite(STDERR, "Missing value for {$key} (add it as a GitHub secret)\n");
        exit(1);
    }
    $line = $key.'='.(preg_match('/[\s#"]/', $value) ? '"'.addcslashes($value, '"').'"' : $value);
    $env = preg_match("/^{$key}=.*$/m", $env)
        ? preg_replace("/^{$key}=.*$/m", $line, $env)
        : rtrim($env)."\n{$line}\n";
}

file_put_contents(__DIR__.'/../.env', $env);
echo "Prepared .env for version {$tag} (code {$versionCode})\n";
