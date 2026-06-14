<?php

use Illuminate\Support\Str;

return [
    'name' => env('HORIZON_NAME', env('APP_NAME', 'FreelanceFlow')),
    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),
    'use' => env('HORIZON_REDIS_CONNECTION', 'default'),
    'prefix' => env('HORIZON_PREFIX', Str::slug(env('APP_NAME', 'freelanceflow'), '_').'_horizon:'),
    'middleware' => ['web'],

    'waits' => [
        'redis:default' => (int) env('HORIZON_WAIT_DEFAULT', 60),
        'redis:emails' => (int) env('HORIZON_WAIT_EMAILS', 120),
        'redis:notifications' => (int) env('HORIZON_WAIT_NOTIFICATIONS', 60),
        'redis:low' => (int) env('HORIZON_WAIT_LOW', 300),
    ],

    'trim' => [
        'recent' => (int) env('HORIZON_TRIM_RECENT', 60),
        'pending' => (int) env('HORIZON_TRIM_PENDING', 60),
        'completed' => (int) env('HORIZON_TRIM_COMPLETED', 60),
        'recent_failed' => (int) env('HORIZON_TRIM_RECENT_FAILED', 10080),
        'failed' => (int) env('HORIZON_TRIM_FAILED', 10080),
        'monitored' => (int) env('HORIZON_TRIM_MONITORED', 10080),
    ],

    'silenced' => [],
    'silenced_tags' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job' => (int) env('HORIZON_METRIC_SNAPSHOTS_JOBS', 24),
            'queue' => (int) env('HORIZON_METRIC_SNAPSHOTS_QUEUES', 24),
        ],
    ],

    'fast_termination' => (bool) env('HORIZON_FAST_TERMINATION', false),
    'memory_limit' => (int) env('HORIZON_MEMORY_LIMIT', 128),

    'defaults' => [
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => (int) env('HORIZON_DEFAULT_MIN_PROCESSES', 1),
            'maxProcesses' => (int) env('HORIZON_DEFAULT_MAX_PROCESSES', 5),
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'maxTime' => 0,
            'maxJobs' => 500,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
        'supervisor-emails' => [
            'connection' => 'redis',
            'queue' => ['emails'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => (int) env('HORIZON_EMAIL_MIN_PROCESSES', 1),
            'maxProcesses' => (int) env('HORIZON_EMAIL_MAX_PROCESSES', 3),
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'maxTime' => 0,
            'maxJobs' => 300,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
        'supervisor-notifications' => [
            'connection' => 'redis',
            'queue' => ['notifications'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => (int) env('HORIZON_NOTIFICATION_MIN_PROCESSES', 1),
            'maxProcesses' => (int) env('HORIZON_NOTIFICATION_MAX_PROCESSES', 3),
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'maxTime' => 0,
            'maxJobs' => 300,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
        'supervisor-low' => [
            'connection' => 'redis',
            'queue' => ['low'],
            'balance' => 'simple',
            'processes' => (int) env('HORIZON_LOW_PROCESSES', 1),
            'maxTime' => 0,
            'maxJobs' => 100,
            'memory' => 128,
            'tries' => 2,
            'timeout' => 300,
            'nice' => 10,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-default' => [],
            'supervisor-emails' => [],
            'supervisor-notifications' => [],
            'supervisor-low' => [],
        ],
        'local' => [
            'supervisor-default' => [
                'balance' => 'simple',
                'maxProcesses' => 2,
            ],
            'supervisor-emails' => [
                'balance' => 'simple',
                'maxProcesses' => 2,
            ],
            'supervisor-notifications' => [
                'balance' => 'simple',
                'maxProcesses' => 2,
            ],
            'supervisor-low' => [
                'processes' => 1,
            ],
        ],
        'testing' => [
            'supervisor-default' => [
                'balance' => 'simple',
                'maxProcesses' => 1,
            ],
            'supervisor-emails' => [
                'balance' => 'simple',
                'maxProcesses' => 1,
            ],
            'supervisor-notifications' => [
                'balance' => 'simple',
                'maxProcesses' => 1,
            ],
            'supervisor-low' => [
                'processes' => 1,
            ],
        ],
    ],

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],
];
