<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const STATUS_SUMMARY_URL = 'http://127.0.0.1/apache-status?auto';
const STATUS_DETAIL_URL = 'http://127.0.0.1/apache-status';
const OUTPUT_CACHE_LOG_PATHS = [
    'C:/xampp2025/apache/logs/cache-status.log',
    'E:/Storage/Logs/cache-status.log',
    'C:/xampp/apache/logs/cache-status.log',
];
const OUTPUT_CACHE_SAMPLE_LINES = 2000;
const DEFAULT_BLOCKLIST_STORAGE_PATH = __DIR__ . '/data/ip-blocklist.json';
const DEFAULT_APACHE_INCLUDE_PATH = __DIR__ . '/data/apache-ip-blocklist.conf';
const DEFAULT_HISTORY_STORAGE_DIR = __DIR__ . '/data/history';
const DEFAULT_HISTORY_SAMPLE_INTERVAL_SECONDS = 60;
const DEFAULT_HISTORY_RETENTION_DAYS = 35;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleMutationRequest();
}

try {
    sendJson(200, [
        'ok' => true,
        'timestamp' => gmdate('c'),
        'data' => buildDashboardData(),
    ]);
} catch (Throwable $e) {
    sendJson(500, [
        'ok' => false,
        'timestamp' => gmdate('c'),
        'error' => $e->getMessage(),
    ]);
}

function sendJson(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function loadRuntimeConfig(): array
{
    static $config;

    if (is_array($config)) {
        return $config;
    }

    $config = [
        'blocklist_storage_path' => DEFAULT_BLOCKLIST_STORAGE_PATH,
        'apache_include_path' => DEFAULT_APACHE_INCLUDE_PATH,
        'apache_include_hint' => '',
        'admin_key' => '',
        'apache_configtest_command' => '',
        'apache_reload_command' => '',
        'history_storage_dir' => DEFAULT_HISTORY_STORAGE_DIR,
        'history_sample_interval_seconds' => (string) DEFAULT_HISTORY_SAMPLE_INTERVAL_SECONDS,
        'history_retention_days' => (string) DEFAULT_HISTORY_RETENTION_DAYS,
    ];

    $configFile = __DIR__ . '/config.local.php';

    if (is_file($configFile)) {
        $loaded = require $configFile;

        if (is_array($loaded)) {
            foreach ($loaded as $key => $value) {
                if (array_key_exists($key, $config) && $value !== null) {
                    $config[$key] = (string) $value;
                }
            }
        }
    }

    $envMap = [
        'blocklist_storage_path' => 'SERVER_STATUS_BLOCKLIST_STORAGE_PATH',
        'apache_include_path' => 'SERVER_STATUS_APACHE_INCLUDE_PATH',
        'apache_include_hint' => 'SERVER_STATUS_APACHE_INCLUDE_HINT',
        'admin_key' => 'SERVER_STATUS_ADMIN_KEY',
        'apache_configtest_command' => 'SERVER_STATUS_APACHE_CONFIGTEST_COMMAND',
        'apache_reload_command' => 'SERVER_STATUS_APACHE_RELOAD_COMMAND',
        'history_storage_dir' => 'SERVER_STATUS_HISTORY_STORAGE_DIR',
        'history_sample_interval_seconds' => 'SERVER_STATUS_HISTORY_SAMPLE_INTERVAL_SECONDS',
        'history_retention_days' => 'SERVER_STATUS_HISTORY_RETENTION_DAYS',
    ];

    foreach ($envMap as $configKey => $envKey) {
        $value = getenv($envKey);

        if (is_string($value) && trim($value) !== '') {
            $config[$configKey] = trim($value);
        }
    }

    $config['blocklist_storage_path'] = normalizePath((string) $config['blocklist_storage_path']);
    $config['apache_include_path'] = normalizePath((string) $config['apache_include_path']);
    $config['history_storage_dir'] = normalizePath((string) $config['history_storage_dir']);
    $config['history_sample_interval_seconds'] = max(10, (int) ($config['history_sample_interval_seconds'] ?? DEFAULT_HISTORY_SAMPLE_INTERVAL_SECONDS));
    $config['history_retention_days'] = max(2, (int) ($config['history_retention_days'] ?? DEFAULT_HISTORY_RETENTION_DAYS));

    return $config;
}

function normalizePath(string $path): string
{
    $trimmed = trim($path);

    if ($trimmed === '') {
        return $trimmed;
    }

    return str_replace('\\', '/', $trimmed);
}

function fetchStatus(string $url): string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_FAILONERROR => false,
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body !== false && $code >= 200 && $code < 300) {
            return (string) $body;
        }

        throw new RuntimeException($error !== '' ? $error : 'Apache status endpoint returned HTTP ' . $code);
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 4,
            'ignore_errors' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);

    if ($body === false) {
        throw new RuntimeException('Could not read Apache status endpoint');
    }

    return $body;
}

function castValue(string $value)
{
    if (is_numeric($value)) {
        return str_contains($value, '.') ? (float) $value : (int) $value;
    }

    return $value;
}

function parseScoreboard(string $scoreboard): array
{
    $labels = [
        '_' => 'Waiting',
        'S' => 'Starting',
        'R' => 'Reading',
        'W' => 'Sending',
        'K' => 'Keepalive',
        'D' => 'DNS',
        'C' => 'Closing',
        'L' => 'Logging',
        'G' => 'Graceful',
        'I' => 'Idle Cleanup',
        '.' => 'Open Slot',
    ];

    $counts = [];
    $length = strlen($scoreboard);

    for ($i = 0; $i < $length; $i++) {
        $symbol = $scoreboard[$i];
        $label = $labels[$symbol] ?? ('State ' . $symbol);

        if (!isset($counts[$label])) {
            $counts[$label] = 0;
        }

        $counts[$label]++;
    }

    arsort($counts);

    return $counts;
}

function parseStatus(string $raw): array
{
    $data = [];

    foreach (preg_split('/\r\n|\r|\n/', trim($raw)) as $line) {
        if (!str_contains($line, ':')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode(':', $line, 2));
        $data[$key] = castValue($value);
    }

    $scoreboard = isset($data['Scoreboard']) ? (string) $data['Scoreboard'] : '';
    $busy = isset($data['BusyWorkers']) ? (int) $data['BusyWorkers'] : 0;
    $idle = isset($data['IdleWorkers']) ? (int) $data['IdleWorkers'] : 0;
    $totalWorkers = $busy + $idle;

    $data['ScoreboardStates'] = parseScoreboard($scoreboard);
    $data['WorkerCapacity'] = $totalWorkers;
    $data['BusyPercent'] = $totalWorkers > 0 ? round(($busy / $totalWorkers) * 100, 1) : 0;
    $data['IdlePercent'] = $totalWorkers > 0 ? round(($idle / $totalWorkers) * 100, 1) : 0;

    return $data;
}

function getApacheProcessCpuPercent(): ?float
{
    if (class_exists('COM')) {
        try {
            $wmi = new COM('winmgmts://./root/cimv2');
            $processes = $wmi->ExecQuery(
                "SELECT Name, PercentProcessorTime FROM Win32_PerfFormattedData_PerfProc_Process WHERE Name LIKE 'httpd%'"
            );

            $sum = 0.0;
            $found = false;

            foreach ($processes as $process) {
                $value = $process->PercentProcessorTime ?? null;

                if ($value === null || !is_numeric((string) $value)) {
                    continue;
                }

                $sum += (float) $value;
                $found = true;
            }

            if ($found) {
                return round($sum, 4);
            }
        } catch (Throwable $e) {
            // Fall through to shell-based fallback.
        }
    }

    if (function_exists('shell_exec')) {
        $command = "powershell -NoProfile -Command "
            . "\"\$p = Get-CimInstance Win32_PerfFormattedData_PerfProc_Process | "
            . "Where-Object { \$_.Name -like 'httpd*' } | "
            . "Measure-Object -Property PercentProcessorTime -Sum; "
            . "if (\$null -ne \$p.Sum) { [string]\$p.Sum }\"";

        $output = shell_exec($command);

        if (is_string($output)) {
            $value = trim($output);

            if ($value !== '' && is_numeric($value)) {
                return round((float) $value, 4);
            }
        }
    }

    return null;
}

function normalizeText(string $value): string
{
    $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $singleLine = preg_replace('/\s+/', ' ', $decoded);

    return trim((string) $singleLine);
}

function normalizeVHost(string $value): string
{
    $value = trim($value);

    if ($value === '' || $value === '-') {
        return 'Unknown';
    }

    if (preg_match('/^\[(.+)\]:(\d+)$/', $value, $matches) === 1) {
        return $matches[1];
    }

    if (preg_match('/^(.+?):\d+$/', $value, $matches) === 1) {
        return $matches[1];
    }

    return $value;
}

function getRequestModeLabel(string $mode): string
{
    $labels = [
        '_' => 'Waiting',
        'S' => 'Starting',
        'R' => 'Reading',
        'W' => 'Sending',
        'K' => 'Keepalive',
        'D' => 'DNS',
        'C' => 'Closing',
        'L' => 'Logging',
        'G' => 'Graceful',
        'I' => 'Idle Cleanup',
        '.' => 'Open Slot',
    ];

    return $labels[$mode] ?? ('State ' . $mode);
}

function getRequestPressureWeight(string $mode): int
{
    $weights = [
        'W' => 500,
        'R' => 400,
        'D' => 300,
        'S' => 200,
        'G' => 100,
    ];

    return $weights[$mode] ?? 0;
}

function isPressureRequest(array $request): bool
{
    return ((int) ($request['pressureWeight'] ?? 0)) > 0;
}

function isKeepaliveRequest(array $request): bool
{
    return (string) ($request['mode'] ?? '') === 'K';
}

function sortRequestsByPressure(array &$requests): void
{
    usort($requests, static function (array $left, array $right): int {
        $byWeight = ((int) ($right['pressureWeight'] ?? 0)) <=> ((int) ($left['pressureWeight'] ?? 0));

        if ($byWeight !== 0) {
            return $byWeight;
        }

        $byDuration = ((int) ($right['durationMs'] ?? 0)) <=> ((int) ($left['durationMs'] ?? 0));

        if ($byDuration !== 0) {
            return $byDuration;
        }

        return ((float) ($right['cpu'] ?? 0)) <=> ((float) ($left['cpu'] ?? 0));
    });
}

function sortClientEntries(array &$items): void
{
    usort($items, static function (array $left, array $right): int {
        $byRequests = ((int) ($right['requestCount'] ?? 0)) <=> ((int) ($left['requestCount'] ?? 0));

        if ($byRequests !== 0) {
            return $byRequests;
        }

        return strcmp((string) ($left['client'] ?? ''), (string) ($right['client'] ?? ''));
    });
}

function parseCurrentRequests(string $html): array
{
    if (!class_exists('DOMDocument')) {
        return [
            'ActiveRequests' => [],
            'ActiveVHosts' => [],
            'ActiveClients' => [],
            'ActiveClientPairs' => [],
            'ActiveRequestCount' => 0,
            'ActiveVHostCount' => 0,
        ];
    }

    $internalErrors = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($internalErrors);

    if ($loaded === false) {
        return [
            'ActiveRequests' => [],
            'ActiveVHosts' => [],
            'ActiveClients' => [],
            'ActiveClientPairs' => [],
            'ActiveRequestCount' => 0,
            'ActiveVHostCount' => 0,
        ];
    }

    $xpath = new DOMXPath($dom);
    $requestTable = null;
    $headers = [];

    foreach ($xpath->query('//table') as $table) {
        $candidateHeaders = [];

        foreach ($xpath->query('.//tr[1]/*[self::th or self::td]', $table) as $cell) {
            $candidateHeaders[] = normalizeText($cell->textContent);
        }

        if (in_array('VHost', $candidateHeaders, true) && in_array('Request', $candidateHeaders, true)) {
            $requestTable = $table;
            $headers = $candidateHeaders;
            break;
        }
    }

    if ($requestTable === null) {
        return [
            'ActiveRequests' => [],
            'ActiveVHosts' => [],
            'ActiveClients' => [],
            'ActiveClientPairs' => [],
            'ActiveRequestCount' => 0,
            'ActiveVHostCount' => 0,
        ];
    }

    $allRequests = [];

    foreach ($xpath->query('.//tr[position() > 1]', $requestTable) as $row) {
        $cells = [];

        foreach ($xpath->query('./td', $row) as $cell) {
            $cells[] = normalizeText($cell->textContent);
        }

        if ($cells === [] || count($cells) < count($headers)) {
            continue;
        }

        $entry = [];

        foreach ($headers as $index => $header) {
            $entry[$header] = $cells[$index] ?? '';
        }

        $mode = (string) ($entry['M'] ?? '');
        $request = (string) ($entry['Request'] ?? '');

        if ($mode === '.' || $request === '') {
            continue;
        }

        $vhostRaw = (string) ($entry['VHost'] ?? '');

        $allRequests[] = [
            'vhost' => normalizeVHost($vhostRaw),
            'vhostRaw' => $vhostRaw,
            'client' => (string) ($entry['Client'] ?? ''),
            'protocol' => (string) ($entry['Protocol'] ?? ''),
            'request' => $request,
            'mode' => $mode,
            'modeLabel' => getRequestModeLabel($mode),
            'secondsSinceConnection' => castValue((string) ($entry['SS'] ?? '0')),
            'secondsSinceRequest' => castValue((string) ($entry['Req'] ?? '0')),
            'durationMs' => castValue((string) ($entry['Dur'] ?? '0')),
            'cpu' => castValue((string) ($entry['CPU'] ?? '0')),
            'pressureWeight' => getRequestPressureWeight($mode),
        ];
    }

    sortRequestsByPressure($allRequests);

    $pressureRequests = array_values(array_filter($allRequests, 'isPressureRequest'));
    $keepaliveRequests = array_values(array_filter($allRequests, 'isKeepaliveRequest'));

    $vhostMap = [];
    $clientMap = [];
    $clientPairMap = [];

    foreach ($pressureRequests as $request) {
        $vhost = (string) $request['vhost'];
        $client = trim((string) ($request['client'] ?? ''));

        if (!isset($vhostMap[$vhost])) {
            $vhostMap[$vhost] = [
                'vhost' => $vhost,
                'activeRequests' => 0,
                'clientMap' => [],
                'sampleRequest' => (string) $request['request'],
                'maxDurationMs' => 0,
            ];
        }

        $vhostMap[$vhost]['activeRequests']++;
        $vhostMap[$vhost]['maxDurationMs'] = max(
            (int) $vhostMap[$vhost]['maxDurationMs'],
            (int) ($request['durationMs'] ?? 0)
        );

        if ($client === '') {
            continue;
        }

        if (!isset($vhostMap[$vhost]['clientMap'][$client])) {
            $vhostMap[$vhost]['clientMap'][$client] = [
                'client' => $client,
                'requestCount' => 0,
                'sampleRequest' => (string) $request['request'],
            ];
        }

        $vhostMap[$vhost]['clientMap'][$client]['requestCount']++;

        if (!isset($clientMap[$client])) {
            $clientMap[$client] = [
                'client' => $client,
                'requestCount' => 0,
                'sampleRequest' => (string) $request['request'],
                'vhostMap' => [],
            ];
        }

        $clientMap[$client]['requestCount']++;
        $clientMap[$client]['vhostMap'][$vhost] = true;

        $pairKey = $vhost . "\n" . $client;

        if (!isset($clientPairMap[$pairKey])) {
            $clientPairMap[$pairKey] = [
                'vhost' => $vhost,
                'client' => $client,
                'requestCount' => 0,
                'sampleRequest' => (string) $request['request'],
            ];
        }

        $clientPairMap[$pairKey]['requestCount']++;
    }

    $activeVHosts = [];

    foreach ($vhostMap as $entry) {
        $clients = array_values($entry['clientMap']);
        sortClientEntries($clients);

        $activeVHosts[] = [
            'vhost' => $entry['vhost'],
            'activeRequests' => $entry['activeRequests'],
            'uniqueClients' => count($clients),
            'sampleRequest' => $entry['sampleRequest'],
            'maxDurationMs' => $entry['maxDurationMs'],
            'clients' => $clients,
        ];
    }

    usort($activeVHosts, static function (array $left, array $right): int {
        $byRequests = ((int) $right['activeRequests']) <=> ((int) $left['activeRequests']);

        if ($byRequests !== 0) {
            return $byRequests;
        }

        return strcmp((string) $left['vhost'], (string) $right['vhost']);
    });

    $activeClients = [];

    foreach ($clientMap as $entry) {
        $vhosts = array_keys($entry['vhostMap']);
        sort($vhosts, SORT_NATURAL | SORT_FLAG_CASE);

        $activeClients[] = [
            'client' => $entry['client'],
            'requestCount' => $entry['requestCount'],
            'websitesCount' => count($vhosts),
            'vhosts' => $vhosts,
            'sampleRequest' => $entry['sampleRequest'],
        ];
    }

    sortClientEntries($activeClients);

    $activeClientPairs = array_values($clientPairMap);

    usort($activeClientPairs, static function (array $left, array $right): int {
        $byRequests = ((int) ($right['requestCount'] ?? 0)) <=> ((int) ($left['requestCount'] ?? 0));

        if ($byRequests !== 0) {
            return $byRequests;
        }

        $byVhost = strcmp((string) ($left['vhost'] ?? ''), (string) ($right['vhost'] ?? ''));

        if ($byVhost !== 0) {
            return $byVhost;
        }

        return strcmp((string) ($left['client'] ?? ''), (string) ($right['client'] ?? ''));
    });

    return [
        'ActiveRequests' => array_slice($pressureRequests, 0, 20),
        'ActiveVHosts' => $activeVHosts,
        'ActiveClients' => $activeClients,
        'ActiveClientPairs' => $activeClientPairs,
        'ActiveRequestCount' => count($pressureRequests),
        'ActiveVHostCount' => count($activeVHosts),
        'ObservedRequests' => array_slice($allRequests, 0, 20),
        'ObservedRequestCount' => count($allRequests),
        'KeepaliveRequestCount' => count($keepaliveRequests),
        'PressureRequestCount' => count($pressureRequests),
    ];
}

function parseSslSessionCacheStats(string $html): array
{
    $text = normalizeText(strip_tags(str_replace(['<br />', '<br>', '<br/>'], "\n", $html)));

    if (
        preg_match(
            '/total retrieves since starting:\s*([0-9,]+)\s*hit,\s*([0-9,]+)\s*miss/i',
            $text,
            $matches
        ) !== 1
    ) {
        return [
            'SslSessionCacheHits' => null,
            'SslSessionCacheMisses' => null,
            'SslSessionCacheHitPercent' => null,
        ];
    }

    $hits = (int) str_replace(',', '', $matches[1]);
    $misses = (int) str_replace(',', '', $matches[2]);
    $total = $hits + $misses;

    return [
        'SslSessionCacheHits' => $hits,
        'SslSessionCacheMisses' => $misses,
        'SslSessionCacheHitPercent' => $total > 0 ? round(($hits / $total) * 100, 2) : 0.0,
    ];
}

function findFirstReadablePath(array $paths): ?string
{
    foreach ($paths as $path) {
        if (is_readable($path)) {
            return $path;
        }
    }

    return null;
}

function tailFileLines(string $path, int $maxLines): array
{
    $file = new SplFileObject($path, 'r');
    $file->seek(PHP_INT_MAX);
    $lastLine = $file->key();
    $startLine = max(0, $lastLine - $maxLines + 1);
    $lines = [];

    for ($lineNumber = $startLine; $lineNumber <= $lastLine; $lineNumber++) {
        $file->seek($lineNumber);
        $line = trim((string) $file->current());

        if ($line !== '') {
            $lines[] = $line;
        }
    }

    return $lines;
}

function parseOutputCacheLogStats(): array
{
    $path = findFirstReadablePath(OUTPUT_CACHE_LOG_PATHS);

    if ($path === null) {
        return [
            'OutputCacheHits' => null,
            'OutputCacheMisses' => null,
            'OutputCacheHitPercent' => null,
            'OutputCacheDecisions' => 0,
            'OutputCacheSource' => 'unavailable',
            'OutputCacheLogPath' => null,
        ];
    }

    try {
        $lines = tailFileLines($path, OUTPUT_CACHE_SAMPLE_LINES);
    } catch (Throwable $e) {
        return [
            'OutputCacheHits' => null,
            'OutputCacheMisses' => null,
            'OutputCacheHitPercent' => null,
            'OutputCacheDecisions' => 0,
            'OutputCacheSource' => 'log_error',
            'OutputCacheLogPath' => $path,
            'OutputCacheError' => $e->getMessage(),
        ];
    }

    $hits = 0;
    $misses = 0;

    foreach ($lines as $line) {
        $parts = explode('|', $line, 6);

        if (count($parts) < 6) {
            continue;
        }

        $cacheStatus = strtoupper(trim($parts[3]));

        if ($cacheStatus === '' || $cacheStatus === '-') {
            continue;
        }

        if (str_contains($cacheStatus, 'HIT')) {
            $hits++;
            continue;
        }

        if (str_contains($cacheStatus, 'MISS')) {
            $misses++;
        }
    }

    $decisions = $hits + $misses;

    return [
        'OutputCacheHits' => $hits,
        'OutputCacheMisses' => $misses,
        'OutputCacheHitPercent' => $decisions > 0 ? round(($hits / $decisions) * 100, 2) : null,
        'OutputCacheDecisions' => $decisions,
        'OutputCacheSource' => 'x_cache_log',
        'OutputCacheLogPath' => $path,
    ];
}

function loadBlocklistState(): array
{
    $config = loadRuntimeConfig();
    $path = (string) $config['blocklist_storage_path'];
    $entries = [];

    if (is_readable($path)) {
        $decoded = json_decode((string) file_get_contents($path), true);

        if (is_array($decoded) && isset($decoded['entries']) && is_array($decoded['entries'])) {
            foreach ($decoded['entries'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $normalized = normalizeBlockEntry($entry);

                if ($normalized !== null) {
                    $entries[] = $normalized;
                }
            }
        }
    }

    $entries = dedupeAndSortBlockEntries($entries);
    $indexes = buildBlockIndexes($entries);

    return [
        'entries' => $entries,
        'indexes' => $indexes,
        'storagePath' => $path,
        'apacheIncludePath' => (string) $config['apache_include_path'],
        'apacheIncludeHint' => (string) $config['apache_include_hint'],
        'adminKeyConfigured' => ((string) $config['admin_key']) !== '',
        'configTestCommandConfigured' => ((string) $config['apache_configtest_command']) !== '',
        'reloadCommandConfigured' => ((string) $config['apache_reload_command']) !== '',
    ];
}

function normalizeBlockEntry(array $entry): ?array
{
    $ip = trim((string) ($entry['ip'] ?? ''));
    $scope = normalizeBlockScope((string) ($entry['scope'] ?? ''));

    if (!isValidIp($ip) || !isValidBlockScope($scope)) {
        return null;
    }

    $createdAt = trim((string) ($entry['createdAt'] ?? ''));
    $createdAt = $createdAt !== '' ? $createdAt : gmdate('c');
    $note = trim((string) ($entry['note'] ?? ''));

    return [
        'ip' => $ip,
        'scope' => $scope,
        'createdAt' => $createdAt,
        'note' => $note,
    ];
}

function normalizeBlockScope(string $scope): string
{
    $trimmed = trim($scope);

    if ($trimmed === '' || strcasecmp($trimmed, 'global') === 0) {
        return 'global';
    }

    return normalizeVHost($trimmed);
}

function isValidIp(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

function isValidBlockScope(string $scope): bool
{
    if ($scope === 'global') {
        return true;
    }

    if ($scope === '' || $scope === 'Unknown') {
        return false;
    }

    return preg_match('/^[A-Za-z0-9.-]+$/', $scope) === 1;
}

function dedupeAndSortBlockEntries(array $entries): array
{
    $deduped = [];

    foreach ($entries as $entry) {
        $key = $entry['scope'] . "\n" . $entry['ip'];
        $deduped[$key] = $entry;
    }

    $entries = array_values($deduped);

    usort($entries, static function (array $left, array $right): int {
        $byScope = strcmp((string) ($left['scope'] ?? ''), (string) ($right['scope'] ?? ''));

        if ($byScope !== 0) {
            return $byScope;
        }

        return strcmp((string) ($left['ip'] ?? ''), (string) ($right['ip'] ?? ''));
    });

    return $entries;
}

function buildBlockIndexes(array $entries): array
{
    $global = [];
    $scoped = [];

    foreach ($entries as $entry) {
        $ip = (string) ($entry['ip'] ?? '');
        $scope = (string) ($entry['scope'] ?? '');

        if ($scope === 'global') {
            $global[$ip] = true;
            continue;
        }

        if (!isset($scoped[$scope])) {
            $scoped[$scope] = [];
        }

        $scoped[$scope][$ip] = true;
    }

    return [
        'global' => $global,
        'scoped' => $scoped,
    ];
}

function getBlockFlags(string $ip, string $vhost, array $indexes): array
{
    $blockedGlobal = isset($indexes['global'][$ip]);
    $blockedOnVHost = isset($indexes['scoped'][$vhost][$ip]);

    return [
        'blockedGlobal' => $blockedGlobal,
        'blockedOnVHost' => $blockedOnVHost,
        'blocked' => $blockedGlobal || $blockedOnVHost,
    ];
}

function decorateRequestDataWithBlocklist(array $requestData, array $blocklistState): array
{
    $indexes = $blocklistState['indexes'];

    foreach ($requestData['ActiveRequests'] as &$request) {
        $flags = getBlockFlags((string) ($request['client'] ?? ''), (string) ($request['vhost'] ?? ''), $indexes);
        $request = array_merge($request, $flags);
    }
    unset($request);

    foreach ($requestData['ActiveClientPairs'] as &$pair) {
        $flags = getBlockFlags((string) ($pair['client'] ?? ''), (string) ($pair['vhost'] ?? ''), $indexes);
        $pair = array_merge($pair, $flags);
    }
    unset($pair);

    foreach ($requestData['ActiveClients'] as &$client) {
        $ip = (string) ($client['client'] ?? '');
        $blockedGlobal = isset($indexes['global'][$ip]);
        $blockedOnAnyVHost = false;

        foreach (($client['vhosts'] ?? []) as $vhost) {
            if (isset($indexes['scoped'][(string) $vhost][$ip])) {
                $blockedOnAnyVHost = true;
                break;
            }
        }

        $client['blockedGlobal'] = $blockedGlobal;
        $client['blockedOnAnyVHost'] = $blockedOnAnyVHost;
        $client['blocked'] = $blockedGlobal || $blockedOnAnyVHost;
    }
    unset($client);

    foreach ($requestData['ActiveVHosts'] as &$vhost) {
        $blockedClients = 0;

        foreach ($vhost['clients'] as &$client) {
            $flags = getBlockFlags((string) ($client['client'] ?? ''), (string) ($vhost['vhost'] ?? ''), $indexes);
            $client = array_merge($client, $flags);

            if ($client['blocked']) {
                $blockedClients++;
            }
        }
        unset($client);

        $vhost['blockedClients'] = $blockedClients;
    }
    unset($vhost);

    return $requestData;
}

function buildBlocklistSummary(array $blocklistState): array
{
    return [
        'entries' => $blocklistState['entries'],
        'entryCount' => count($blocklistState['entries']),
        'storagePath' => $blocklistState['storagePath'],
        'apacheIncludePath' => $blocklistState['apacheIncludePath'],
        'apacheIncludeHint' => $blocklistState['apacheIncludeHint'],
        'adminKeyConfigured' => $blocklistState['adminKeyConfigured'],
        'configTestCommandConfigured' => $blocklistState['configTestCommandConfigured'],
        'reloadCommandConfigured' => $blocklistState['reloadCommandConfigured'],
    ];
}

function getHistoryWindowDefinitions(): array
{
    return [
        '24h' => ['label' => 'Last 24h', 'seconds' => 24 * 60 * 60],
        '7d' => ['label' => 'Last 7 days', 'seconds' => 7 * 24 * 60 * 60],
        '30d' => ['label' => 'Last 30 days', 'seconds' => 30 * 24 * 60 * 60],
    ];
}

function buildEmptyHistorySummary(): array
{
    $config = loadRuntimeConfig();
    $windows = [];

    foreach (getHistoryWindowDefinitions() as $key => $definition) {
        $expectedSamples = (int) ceil($definition['seconds'] / (int) $config['history_sample_interval_seconds']);

        $windows[$key] = [
            'label' => $definition['label'],
            'seconds' => $definition['seconds'],
            'expectedSamples' => $expectedSamples,
            'capturedSamples' => 0,
            'coveragePercent' => 0.0,
            'sites' => [],
        ];
    }

    return [
        'sampleIntervalSeconds' => (int) $config['history_sample_interval_seconds'],
        'retentionDays' => (int) $config['history_retention_days'],
        'storageDir' => (string) $config['history_storage_dir'],
        'note' => 'Ranking uses estimated req-min from load-bearing requests. Sampling happens when stats.php is requested; use a scheduled curl every minute on the real server for complete coverage.',
        'windows' => $windows,
    ];
}

function buildHistorySiteSample(array $vhost): array
{
    return [
        'vhost' => (string) ($vhost['vhost'] ?? 'Unknown'),
        'requestCount' => (int) ($vhost['activeRequests'] ?? 0),
        'uniqueClients' => (int) ($vhost['uniqueClients'] ?? 0),
        'maxDurationMs' => (int) ($vhost['maxDurationMs'] ?? 0),
        'sampleRequest' => (string) ($vhost['sampleRequest'] ?? ''),
    ];
}

function buildHistoricalSample(array $data): array
{
    $config = loadRuntimeConfig();
    $intervalSeconds = (int) $config['history_sample_interval_seconds'];
    $slotTs = (int) (floor(time() / $intervalSeconds) * $intervalSeconds);
    $sites = array_map('buildHistorySiteSample', $data['ActiveVHosts'] ?? []);

    return [
        'ts' => $slotTs,
        'timestamp' => gmdate('c', $slotTs),
        'intervalSeconds' => $intervalSeconds,
        'pressureRequestCount' => (int) ($data['PressureRequestCount'] ?? 0),
        'keepaliveRequestCount' => (int) ($data['KeepaliveRequestCount'] ?? 0),
        'busyWorkers' => (int) ($data['BusyWorkers'] ?? 0),
        'apacheCpuPercent' => isset($data['ApacheCpuPercent']) && is_numeric((string) $data['ApacheCpuPercent'])
            ? (float) $data['ApacheCpuPercent']
            : null,
        'sites' => $sites,
    ];
}

function recordHistoricalSample(array $data): array
{
    $config = loadRuntimeConfig();
    $storageDir = (string) $config['history_storage_dir'];
    $sample = buildHistoricalSample($data);

    if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
        throw new RuntimeException('Failed to create history storage directory: ' . $storageDir);
    }

    $lockHandle = fopen($storageDir . '/.history.lock', 'c+');

    if ($lockHandle === false) {
        throw new RuntimeException('Failed to open history lock file.');
    }

    $slotFile = $storageDir . '/latest-slot.txt';
    $recorded = false;

    try {
        if (!flock($lockHandle, LOCK_EX)) {
            throw new RuntimeException('Failed to lock history storage.');
        }

        $latestSlot = is_file($slotFile) ? trim((string) file_get_contents($slotFile)) : '';

        if ($latestSlot !== (string) $sample['ts']) {
            $historyPath = $storageDir . '/' . gmdate('Y-m-d', (int) $sample['ts']) . '.jsonl';
            $line = json_encode($sample, JSON_UNESCAPED_SLASHES);

            if (!is_string($line)) {
                throw new RuntimeException('Failed to encode historical sample.');
            }

            if (file_put_contents($historyPath, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
                throw new RuntimeException('Failed to append historical sample.');
            }

            writeFileAtomically($slotFile, (string) $sample['ts']);
            $recorded = true;
        }
    } finally {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }

    purgeOldHistoryFiles($storageDir, (int) $config['history_retention_days']);

    return [
        'recorded' => $recorded,
        'timestamp' => $sample['timestamp'],
        'slotTs' => $sample['ts'],
    ];
}

function purgeOldHistoryFiles(string $storageDir, int $retentionDays): void
{
    $cutoff = strtotime('-' . max(1, $retentionDays) . ' days');

    foreach (glob($storageDir . '/*.jsonl') ?: [] as $path) {
        $basename = basename($path, '.jsonl');
        $fileTs = strtotime($basename . ' 00:00:00 UTC');

        if ($fileTs !== false && $fileTs < $cutoff) {
            @unlink($path);
        }
    }
}

function readHistoricalSamples(): array
{
    $config = loadRuntimeConfig();
    $storageDir = (string) $config['history_storage_dir'];
    $history = buildEmptyHistorySummary();

    if (!is_dir($storageDir)) {
        return $history;
    }

    $definitions = getHistoryWindowDefinitions();
    $maxSeconds = max(array_column($definitions, 'seconds'));
    $cutoffTs = time() - $maxSeconds;
    $samples = [];

    foreach (glob($storageDir . '/*.jsonl') ?: [] as $path) {
        $basename = basename($path, '.jsonl');
        $fileTs = strtotime($basename . ' 00:00:00 UTC');

        if ($fileTs !== false && $fileTs < strtotime('-31 days')) {
            continue;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            continue;
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $decoded = json_decode(trim($line), true);

                if (!is_array($decoded)) {
                    continue;
                }

                $ts = (int) ($decoded['ts'] ?? 0);

                if ($ts < $cutoffTs) {
                    continue;
                }

                $samples[] = $decoded;
            }
        } finally {
            fclose($handle);
        }
    }

    usort($samples, static function (array $left, array $right): int {
        return ((int) ($left['ts'] ?? 0)) <=> ((int) ($right['ts'] ?? 0));
    });

    foreach ($definitions as $key => $definition) {
        $cutoff = time() - $definition['seconds'];
        $expectedSamples = (int) ceil($definition['seconds'] / (int) $config['history_sample_interval_seconds']);
        $capturedSamples = 0;
        $siteMap = [];

        foreach ($samples as $sample) {
            $ts = (int) ($sample['ts'] ?? 0);

            if ($ts < $cutoff) {
                continue;
            }

            $capturedSamples++;

            foreach (($sample['sites'] ?? []) as $site) {
                if (!is_array($site)) {
                    continue;
                }

                $vhost = (string) ($site['vhost'] ?? 'Unknown');

                if (!isset($siteMap[$vhost])) {
                    $siteMap[$vhost] = [
                        'vhost' => $vhost,
                        'requestSecondsEstimate' => 0,
                        'requestCountTotal' => 0,
                        'sampleHits' => 0,
                        'peakRequests' => 0,
                        'maxUniqueClients' => 0,
                        'peakDurationMs' => 0,
                        'lastSeenAt' => '',
                        'sampleRequest' => '',
                    ];
                }

                $requestCount = (int) ($site['requestCount'] ?? 0);
                $siteMap[$vhost]['requestSecondsEstimate'] += $requestCount * (int) ($sample['intervalSeconds'] ?? $config['history_sample_interval_seconds']);
                $siteMap[$vhost]['requestCountTotal'] += $requestCount;
                $siteMap[$vhost]['sampleHits']++;
                $siteMap[$vhost]['peakRequests'] = max($siteMap[$vhost]['peakRequests'], $requestCount);
                $siteMap[$vhost]['maxUniqueClients'] = max($siteMap[$vhost]['maxUniqueClients'], (int) ($site['uniqueClients'] ?? 0));
                $siteMap[$vhost]['peakDurationMs'] = max($siteMap[$vhost]['peakDurationMs'], (int) ($site['maxDurationMs'] ?? 0));
                $siteMap[$vhost]['lastSeenAt'] = gmdate('c', $ts);
                $siteMap[$vhost]['sampleRequest'] = (string) ($site['sampleRequest'] ?? '');
            }
        }

        $sites = array_values($siteMap);

        foreach ($sites as &$site) {
            $site['requestMinutesEstimate'] = round(((int) $site['requestSecondsEstimate']) / 60, 1);
            $site['avgConcurrentRequests'] = $site['sampleHits'] > 0
                ? round(((int) $site['requestCountTotal']) / ((int) $site['sampleHits']), 2)
                : 0.0;
        }
        unset($site);

        usort($sites, static function (array $left, array $right): int {
            $bySeconds = ((int) ($right['requestSecondsEstimate'] ?? 0)) <=> ((int) ($left['requestSecondsEstimate'] ?? 0));

            if ($bySeconds !== 0) {
                return $bySeconds;
            }

            $byPeak = ((int) ($right['peakRequests'] ?? 0)) <=> ((int) ($left['peakRequests'] ?? 0));

            if ($byPeak !== 0) {
                return $byPeak;
            }

            return strcmp((string) ($left['vhost'] ?? ''), (string) ($right['vhost'] ?? ''));
        });

        $history['windows'][$key] = [
            'label' => $definition['label'],
            'seconds' => $definition['seconds'],
            'expectedSamples' => $expectedSamples,
            'capturedSamples' => $capturedSamples,
            'coveragePercent' => $expectedSamples > 0 ? round(($capturedSamples / $expectedSamples) * 100, 1) : 0.0,
            'sites' => $sites,
        ];
    }

    return $history;
}

function buildDashboardData(): array
{
    $raw = fetchStatus(STATUS_SUMMARY_URL);
    $data = parseStatus($raw);
    $data = array_merge($data, parseOutputCacheLogStats());

    $rawApacheCpu = isset($data['CPULoad']) && is_numeric((string) $data['CPULoad'])
        ? (float) $data['CPULoad']
        : null;

    $data['ApacheStatusCpuLoad'] = $rawApacheCpu;
    $data['ApacheCpuPercent'] = $rawApacheCpu;
    $data['ApacheCpuSource'] = $rawApacheCpu !== null ? 'mod_status' : 'unavailable';

    if (($rawApacheCpu === null || $rawApacheCpu <= 0.0) && (($data['ServerMPM'] ?? '') === 'WinNT')) {
        $fallbackCpu = getApacheProcessCpuPercent();

        if ($fallbackCpu !== null) {
            $data['ApacheCpuPercent'] = $fallbackCpu;
            $data['ApacheCpuSource'] = 'win_process';
        }
    }

    try {
        $detailRaw = fetchStatus(STATUS_DETAIL_URL);
        $requestData = array_merge(parseCurrentRequests($detailRaw), parseSslSessionCacheStats($detailRaw));
        $blocklistState = loadBlocklistState();
        $requestData = decorateRequestDataWithBlocklist($requestData, $blocklistState);
        $data = array_merge($data, $requestData);
        $data['Blocklist'] = buildBlocklistSummary($blocklistState);

        try {
            $data['HistoricalSampling'] = recordHistoricalSample($data);
            $data['History'] = readHistoricalSamples();
        } catch (Throwable $historyError) {
            $data['HistoricalSampling'] = [
                'recorded' => false,
                'timestamp' => gmdate('c'),
                'slotTs' => null,
            ];
            $data['History'] = buildEmptyHistorySummary();
            $data['HistoryError'] = $historyError->getMessage();
        }
    } catch (Throwable $e) {
        $data['ActiveRequests'] = [];
        $data['ActiveVHosts'] = [];
        $data['ActiveClients'] = [];
        $data['ActiveClientPairs'] = [];
        $data['ActiveRequestCount'] = 0;
        $data['ActiveVHostCount'] = 0;
        $data['ObservedRequests'] = [];
        $data['ObservedRequestCount'] = 0;
        $data['KeepaliveRequestCount'] = 0;
        $data['PressureRequestCount'] = 0;
        $data['SslSessionCacheHits'] = null;
        $data['SslSessionCacheMisses'] = null;
        $data['SslSessionCacheHitPercent'] = null;
        $data['DetailError'] = $e->getMessage();
        $data['Blocklist'] = buildBlocklistSummary(loadBlocklistState());
        $data['HistoricalSampling'] = [
            'recorded' => false,
            'timestamp' => gmdate('c'),
            'slotTs' => null,
        ];
        $data['History'] = buildEmptyHistorySummary();
    }

    return $data;
}

function handleMutationRequest(): void
{
    try {
        $config = loadRuntimeConfig();
        $adminKey = (string) $config['admin_key'];

        if ($adminKey === '') {
            sendJson(403, [
                'ok' => false,
                'timestamp' => gmdate('c'),
                'error' => 'Admin key is not configured on this server.',
            ]);
        }

        $providedKey = trim((string) ($_SERVER['HTTP_X_ADMIN_KEY'] ?? ''));

        if (!hash_equals($adminKey, $providedKey)) {
            sendJson(403, [
                'ok' => false,
                'timestamp' => gmdate('c'),
                'error' => 'Invalid admin key.',
            ]);
        }

        $payload = readRequestPayload();
        $action = (string) ($payload['action'] ?? '');

        if ($action === 'block_ip') {
            $result = blockIp($payload);
        } elseif ($action === 'unblock_ip') {
            $result = unblockIp($payload);
        } else {
            throw new InvalidArgumentException('Unknown action.');
        }

        sendJson(200, [
            'ok' => true,
            'timestamp' => gmdate('c'),
            'data' => $result,
        ]);
    } catch (Throwable $e) {
        sendJson(400, [
            'ok' => false,
            'timestamp' => gmdate('c'),
            'error' => $e->getMessage(),
        ]);
    }
}

function readRequestPayload(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Invalid JSON payload.');
        }

        return $decoded;
    }

    return $_POST;
}

function blockIp(array $payload): array
{
    $ip = trim((string) ($payload['ip'] ?? ''));
    $scope = normalizeBlockScope((string) ($payload['scope'] ?? 'global'));
    $note = trim((string) ($payload['note'] ?? ''));

    if (!isValidIp($ip)) {
        throw new InvalidArgumentException('Invalid IP address.');
    }

    if (!isValidBlockScope($scope)) {
        throw new InvalidArgumentException('Invalid block scope.');
    }

    $state = loadBlocklistState();
    $entries = $state['entries'];

    $entries[] = [
        'ip' => $ip,
        'scope' => $scope,
        'createdAt' => gmdate('c'),
        'note' => $note,
    ];

    $saveResult = persistBlocklist($entries);

    return [
        'message' => $scope === 'global'
            ? sprintf('Blocked %s globally.', $ip)
            : sprintf('Blocked %s for %s.', $ip, $scope),
        'blocklist' => buildBlocklistSummary($saveResult['state']),
        'apply' => $saveResult['apply'],
    ];
}

function unblockIp(array $payload): array
{
    $ip = trim((string) ($payload['ip'] ?? ''));
    $scope = normalizeBlockScope((string) ($payload['scope'] ?? 'global'));

    if (!isValidIp($ip)) {
        throw new InvalidArgumentException('Invalid IP address.');
    }

    if (!isValidBlockScope($scope)) {
        throw new InvalidArgumentException('Invalid block scope.');
    }

    $state = loadBlocklistState();
    $entries = array_values(array_filter($state['entries'], static function (array $entry) use ($ip, $scope): bool {
        return !((string) $entry['ip'] === $ip && (string) $entry['scope'] === $scope);
    }));

    $saveResult = persistBlocklist($entries);

    return [
        'message' => $scope === 'global'
            ? sprintf('Removed global block for %s.', $ip)
            : sprintf('Removed %s block for %s.', $scope, $ip),
        'blocklist' => buildBlocklistSummary($saveResult['state']),
        'apply' => $saveResult['apply'],
    ];
}

function persistBlocklist(array $entries): array
{
    $entries = dedupeAndSortBlockEntries(array_values(array_filter(array_map('normalizeBlockEntry', $entries))));
    $config = loadRuntimeConfig();
    $storagePath = (string) $config['blocklist_storage_path'];
    $apacheIncludePath = (string) $config['apache_include_path'];

    $previousJson = is_file($storagePath) ? (string) file_get_contents($storagePath) : null;
    $previousApache = is_file($apacheIncludePath) ? (string) file_get_contents($apacheIncludePath) : null;

    $jsonPayload = json_encode([
        'version' => 1,
        'updatedAt' => gmdate('c'),
        'entries' => $entries,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (!is_string($jsonPayload)) {
        throw new RuntimeException('Failed to encode blocklist JSON.');
    }

    $apachePayload = buildApacheBlocklistConfig($entries);

    writeFileAtomically($storagePath, $jsonPayload . PHP_EOL);
    writeFileAtomically($apacheIncludePath, $apachePayload);

    try {
        $apply = applyApacheChanges();
    } catch (Throwable $e) {
        restoreFileState($storagePath, $previousJson);
        restoreFileState($apacheIncludePath, $previousApache);
        throw $e;
    }

    return [
        'state' => loadBlocklistState(),
        'apply' => $apply,
    ];
}

function buildApacheBlocklistConfig(array $entries): string
{
    $lines = [
        '# Auto-generated by server-status. Do not edit manually.',
        '# Include this file from Apache server config or from a vhost config.',
        '# Example:',
        '#   IncludeOptional "/absolute/path/to/apache-ip-blocklist.conf"',
        '',
    ];

    if ($entries === []) {
        $lines[] = '# No blocked IP entries.';
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    $globalIps = [];
    $scopedIps = [];

    foreach ($entries as $entry) {
        $scope = (string) ($entry['scope'] ?? '');
        $ip = (string) ($entry['ip'] ?? '');

        if ($scope === 'global') {
            $globalIps[] = $ip;
            continue;
        }

        if (!isset($scopedIps[$scope])) {
            $scopedIps[$scope] = [];
        }

        $scopedIps[$scope][] = $ip;
    }

    if ($globalIps !== []) {
        $lines[] = 'RewriteEngine On';
        $lines[] = '';
        $lines[] = '# Global IP blocks';
        foreach (buildIpConditions($globalIps) as $condition) {
            $lines[] = $condition;
        }
        $lines[] = 'RewriteRule ^ - [F,L]';
        $lines[] = '';
    }

    foreach ($scopedIps as $scope => $ips) {
        if ($ips === []) {
            continue;
        }

        if (!in_array('RewriteEngine On', $lines, true)) {
            $lines[] = 'RewriteEngine On';
            $lines[] = '';
        }

        $lines[] = '# IP blocks for ' . $scope;
        $lines[] = 'RewriteCond %{HTTP_HOST} ^' . preg_quote($scope, '#') . '(?::[0-9]+)?$ [NC]';
        foreach (buildIpConditions($ips) as $condition) {
            $lines[] = $condition;
        }
        $lines[] = 'RewriteRule ^ - [F,L]';
        $lines[] = '';
    }

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

function buildIpConditions(array $ips): array
{
    $ips = array_values(array_unique($ips));
    $conditions = [];
    $lastIndex = count($ips) - 1;

    foreach ($ips as $index => $ip) {
        $suffix = $index < $lastIndex ? ' [OR]' : '';
        $conditions[] = 'RewriteCond %{REMOTE_ADDR} ^' . preg_quote($ip, '#') . '$' . $suffix;
    }

    return $conditions;
}

function writeFileAtomically(string $path, string $content): void
{
    $directory = dirname($path);

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Failed to create directory: ' . $directory);
    }

    $tempPath = $path . '.' . uniqid('tmp', true);

    if (file_put_contents($tempPath, $content, LOCK_EX) === false) {
        throw new RuntimeException('Failed to write file: ' . $path);
    }

    if (!@rename($tempPath, $path)) {
        @unlink($tempPath);
        throw new RuntimeException('Failed to replace file: ' . $path);
    }
}

function restoreFileState(string $path, ?string $content): void
{
    if ($content === null) {
        if (is_file($path)) {
            @unlink($path);
        }

        return;
    }

    writeFileAtomically($path, $content);
}

function applyApacheChanges(): array
{
    $config = loadRuntimeConfig();
    $configTestCommand = trim((string) $config['apache_configtest_command']);
    $reloadCommand = trim((string) $config['apache_reload_command']);

    $configTest = runOptionalCommand($configTestCommand);

    if ($configTest['configured'] && !$configTest['ok']) {
        throw new RuntimeException('Apache config test failed: ' . $configTest['summary']);
    }

    $reload = runOptionalCommand($reloadCommand);

    return [
        'configTest' => $configTest,
        'reload' => $reload,
    ];
}

function runOptionalCommand(string $command): array
{
    if ($command === '') {
        return [
            'configured' => false,
            'ok' => null,
            'summary' => 'No command configured.',
            'output' => [],
        ];
    }

    if (!function_exists('exec')) {
        return [
            'configured' => true,
            'ok' => false,
            'summary' => 'PHP exec() is unavailable.',
            'output' => [],
        ];
    }

    $output = [];
    $exitCode = 0;
    exec($command . ' 2>&1', $output, $exitCode);

    return [
        'configured' => true,
        'ok' => $exitCode === 0,
        'summary' => trim($output[0] ?? ('Exit code ' . $exitCode)),
        'output' => $output,
    ];
}
