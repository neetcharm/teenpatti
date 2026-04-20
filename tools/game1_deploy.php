<?php
declare(strict_types=1);

/**
 * Game1 direct deploy endpoint (hardcoded token mode)
 *
 * Trigger examples:
 *   GET  /tools/game1_deploy.php?token=G1DEPLOY_2026_LIVE
 *   POST /tools/game1_deploy.php  (token in body: token=G1DEPLOY_2026_LIVE)
 *
 * Optional request overrides:
 *   repo=owner/repo
 *   branch=main
 *   github_token=ghp_xxx  (required only for private repo)
 */

header('Content-Type: text/plain; charset=UTF-8');

$hardcodedToken = 'G1DEPLOY_2026_LIVE';

$providedToken = (string) (
    $_GET['token']
    ?? $_POST['token']
    ?? ($_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '')
);

if ($providedToken === '' || !hash_equals($hardcodedToken, $providedToken)) {
    http_response_code(403);
    echo "Access denied.\n";
    exit;
}

$repo = trim((string) ($_GET['repo'] ?? $_POST['repo'] ?? 'neetcharm/game'));
$branch = trim((string) ($_GET['branch'] ?? $_POST['branch'] ?? 'main'));

if ($repo === '') {
    $repo = 'neetcharm/game';
}
if ($branch === '') {
    $branch = 'main';
}

// Hardcoded live config requested by user.
putenv('DEPLOY_TRIGGER_TOKEN=' . $hardcodedToken);
putenv('DEPLOY_GITHUB_REPO=' . $repo);
putenv('DEPLOY_BRANCH=' . $branch);
putenv('DEPLOY_ALLOW_GIT_PULL=1');
putenv('DEPLOY_WRITE_ENV=1');
putenv('DEPLOY_RUN_ARTISAN=1');
putenv('DEPLOY_RUN_MIGRATIONS=1');

putenv('LIVE_APP_ENV=production');
putenv('LIVE_APP_DEBUG=false');
putenv('LIVE_APP_URL=https://game.ezycry.com');
putenv('LIVE_DB_HOST=localhost');
putenv('LIVE_DB_PORT=3306');
putenv('LIVE_DB_DATABASE=u898978846_prakash');
putenv('LIVE_DB_USERNAME=u898978846_prakash');
putenv('LIVE_DB_PASSWORD=Kanishk@123#');

// GitHub token can be supplied at runtime for private repo deploy.
$githubToken = (string) (
    $_GET['github_token']
    ?? $_POST['github_token']
    ?? ($_SERVER['HTTP_X_GITHUB_TOKEN'] ?? '')
);

if ($githubToken !== '') {
    putenv('DEPLOY_GITHUB_TOKEN=' . $githubToken);
}

require __DIR__ . '/github_deploy.php';
