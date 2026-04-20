<?php
/**
 * Server-side cache clearing + diagnostics
 * Usage: /tools/clear_cache.php?token=G1DEPLOY_2026_LIVE
 */
header('Content-Type: text/plain; charset=UTF-8');

$token = $_GET['token'] ?? $_POST['token'] ?? '';
if ($token !== 'G1DEPLOY_2026_LIVE') {
    http_response_code(403);
    echo "Access denied.\n";
    exit;
}

$projectRoot = realpath(__DIR__);
// On the live server, .env is in core/ subfolder of the project root
// Try multiple possible locations
$candidates = [
    $projectRoot . DIRECTORY_SEPARATOR . 'core',
    realpath($projectRoot . '/..') . DIRECTORY_SEPARATOR . 'core',
    '/home/u898978846/domains/ezycry.com/public_html/game/core',
];

$coreDir = null;
foreach ($candidates as $c) {
    if (is_dir($c)) {
        $coreDir = $c;
        break;
    }
}

if (!$coreDir) {
    echo "ERROR: Could not find core/ directory.\n";
    echo "Searched: " . implode(', ', $candidates) . "\n";
    echo "Script dir: " . __DIR__ . "\n";
    echo "Project root: " . $projectRoot . "\n";
    exit(1);
}

$envFile = $coreDir . DIRECTORY_SEPARATOR . '.env';

echo "=== GAME CACHE CLEAR + DIAGNOSTICS ===\n\n";

// 1. Show current .env key values (masked)
echo "[1] Current .env Analysis:\n";
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $parts = explode('=', $line, 2);
        $key = trim($parts[0]);
        $val = trim($parts[1] ?? '', " \t\n\r\0\x0B\"");
        
        // Show important config keys
        $showKeys = ['APP_ENV','APP_DEBUG','APP_URL','DB_HOST','DB_DATABASE',
                     'CACHE_STORE','SESSION_DRIVER','QUEUE_CONNECTION'];
        if (in_array($key, $showKeys)) {
            echo "  {$key} = {$val}\n";
        }
    }
} else {
    echo "  WARNING: .env file NOT found!\n";
}

// 2. Clear bootstrap cache
echo "\n[2] Clearing Bootstrap Cache:\n";
$cacheDir = $coreDir . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache';
$cleared = 0;
if (is_dir($cacheDir)) {
    $files = glob($cacheDir . DIRECTORY_SEPARATOR . '*.php') ?: [];
    foreach ($files as $f) {
        if (@unlink($f)) {
            echo "  Deleted: " . basename($f) . "\n";
            $cleared++;
        }
    }
}
echo "  Bootstrap cache files cleared: {$cleared}\n";

// 3. Clear file-based cache  
echo "\n[3] Clearing File Cache:\n";
$fileCacheDir = $coreDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'data';
$cacheCleared = 0;
if (is_dir($fileCacheDir)) {
    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fileCacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($rii as $f) {
        if ($f->isFile() && $f->getFilename() !== '.gitignore') {
            @unlink($f->getRealPath());
            $cacheCleared++;
        }
    }
}
echo "  File cache entries cleared: {$cacheCleared}\n";

// 4. Clear compiled views
echo "\n[4] Clearing Compiled Views:\n";
$viewsDir = $coreDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'views';
$viewsCleared = 0;
if (is_dir($viewsDir)) {
    $files = glob($viewsDir . DIRECTORY_SEPARATOR . '*.php') ?: [];
    foreach ($files as $f) {
        if (@unlink($f)) $viewsCleared++;
    }
}
echo "  Compiled views cleared: {$viewsCleared}\n";

// 5. Clear sessions
echo "\n[5] Clearing File Sessions:\n";
$sessDir = $coreDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'sessions';
$sessCleared = 0;
if (is_dir($sessDir)) {
    $files = glob($sessDir . DIRECTORY_SEPARATOR . '*') ?: [];
    foreach ($files as $f) {
        if (is_file($f) && basename($f) !== '.gitignore') {
            if (@unlink($f)) $sessCleared++;
        }
    }
}
echo "  Sessions cleared: {$sessCleared}\n";

// 6. OPcache reset
echo "\n[6] OPcache:\n";
if (function_exists('opcache_reset')) {
    @opcache_reset();
    echo "  OPcache reset: OK\n";
} else {
    echo "  OPcache not available\n";
}

// 7. Test Teen Patti sync logic (without Laravel)
echo "\n[7] Teen Patti Timer Diagnostics:\n";
$BET_WINDOW = 20;
$HOLD_WINDOW = 15;
$ROUND_DURATION = $BET_WINDOW + $HOLD_WINDOW;
$serverTime = time();
$currentRound = (int) floor($serverTime / $ROUND_DURATION);
$elapsed = $serverTime % $ROUND_DURATION;
$phase = $elapsed < $BET_WINDOW ? 'betting' : 'hold';
$remaining = $elapsed < $BET_WINDOW ? $BET_WINDOW - $elapsed : $ROUND_DURATION - $elapsed;

echo "  Server time: " . date('Y-m-d H:i:s', $serverTime) . " (Unix: {$serverTime})\n";
echo "  Current round: {$currentRound}\n";
echo "  Elapsed in round: {$elapsed}s\n";
echo "  Phase: {$phase}\n";
echo "  Seconds remaining: {$remaining}\n";
echo "  Round duration: {$ROUND_DURATION}s (bet:{$BET_WINDOW} + hold:{$HOLD_WINDOW})\n";

// 8. Try to run artisan
echo "\n[8] Artisan Commands:\n";
if (function_exists('exec')) {
    $phpBin = 'php';
    // Try common PHP paths on Hostinger
    $phpPaths = ['/usr/bin/php', '/usr/local/bin/php', '/opt/alt/php82/usr/bin/php', '/opt/alt/php81/usr/bin/php', 'php'];
    foreach ($phpPaths as $p) {
        $out = [];
        @exec($p . ' --version 2>&1', $out, $exit);
        if ($exit === 0) {
            $phpBin = $p;
            echo "  PHP binary: {$phpBin}\n";
            break;
        }
    }
    
    $cmd = 'cd ' . escapeshellarg($coreDir) . ' && ' . $phpBin . ' artisan optimize:clear 2>&1';
    $output = [];
    exec($cmd, $output, $exitCode);
    echo "  optimize:clear exit code: {$exitCode}\n";
    foreach ($output as $line) echo "    {$line}\n";
    
    $cmd2 = 'cd ' . escapeshellarg($coreDir) . ' && ' . $phpBin . ' artisan config:cache 2>&1';
    $output2 = [];
    exec($cmd2, $output2, $exitCode2);
    echo "  config:cache exit code: {$exitCode2}\n";
    foreach ($output2 as $line) echo "    {$line}\n";
} elseif (function_exists('shell_exec')) {
    echo "  (Using shell_exec fallback)\n";
    $out = shell_exec('cd ' . escapeshellarg($coreDir) . ' && php artisan optimize:clear 2>&1');
    echo "  " . trim((string)$out) . "\n";
    $out2 = shell_exec('cd ' . escapeshellarg($coreDir) . ' && php artisan config:cache 2>&1');
    echo "  " . trim((string)$out2) . "\n";
} else {
    echo "  WARNING: exec/shell_exec not available!\n";
}

// 9. Verify storage directories
echo "\n[9] Storage Directory Check:\n";
$dirs = [
    'storage/app',
    'storage/framework/cache',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache'
];
foreach ($dirs as $d) {
    $full = $coreDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $d);
    if (!is_dir($full)) {
        @mkdir($full, 0755, true);
        echo "  CREATED: {$d}\n";
    } else {
        $writable = is_writable($full) ? 'writable' : 'NOT WRITABLE!';
        echo "  OK: {$d} ({$writable})\n";
    }
}

echo "\n=== CACHE CLEAR COMPLETE ===\n";
echo "Now hard-refresh browser (Ctrl+Shift+R) to reload game.\n";
