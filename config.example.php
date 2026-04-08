<?php

return [
    // Required for any block/unblock action from the dashboard UI.
    'admin_key' => 'change-this-before-use',

    // JSON source of truth for blocks managed by stats.php.
    'blocklist_storage_path' => __DIR__ . '/data/ip-blocklist.json',

    // Apache include file generated from the JSON blocklist.
    'apache_include_path' => __DIR__ . '/data/apache-ip-blocklist.conf',

    // Optional hint shown in the UI so you remember what to include on the real server.
    'apache_include_hint' => 'IncludeOptional "/absolute/path/to/server-status/data/apache-ip-blocklist.conf"',

    // Optional. If set, stats.php runs this before reload and rolls back if it fails.
    'apache_configtest_command' => '',

    // Optional. If set, stats.php tries to reload Apache after blocklist changes.
    'apache_reload_command' => '',

    // Historical load sampling. A sample is stored whenever stats.php is requested,
    // but no more often than this interval.
    'history_storage_dir' => __DIR__ . '/data/history',
    'history_sample_interval_seconds' => 60,
    'history_retention_days' => 35,
];
