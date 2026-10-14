<?php

return [
    'app_id' => env('VINKIUS_APP_ID'),
    'app_key' => env('VINKIUS_APP_KEY'),
    'base_url' => env('VINKIUS_BASE_URL', 'https://api.vinkius.com'),
    'timeout' => (int) env('VINKIUS_TIMEOUT', 30000),
    'max_retries' => (int) env('VINKIUS_MAX_RETRIES', 2),
    'user_agent' => env('VINKIUS_USER_AGENT', 'vinkius-connect-laravel'),

    /*
     * Determines the external_id sent to the Vinkius API for a given Laravel
     * user. May be a string property, a callable taking the model and
     * returning a string, or null to fall back to (string) $model->getKey().
     *
     * The value must be URL-path-safe (no whitespace, "/", or "\\").
     */
    'external_id' => null,

    /*
     * Capability name namespacing. Defaults to "{connector}__{name}".
     * Must produce a valid tool name for the target LLM platform:
     *   OpenAI     [a-zA-Z0-9_-]{1,64}
     *   Anthropic  [a-zA-Z0-9_-]{1,128}
     *   Gemini     ^[a-zA-Z_][a-zA-Z0-9_]*$  (max 64, no hyphen)
     * The default separator "__" is rejected by Gemini; use "_" instead when
     * the primary target is Gemini.
     */
    'namespace' => null,

    /*
     * Optional local mirror of observed connector state. Off by default — the
     * platform is the source of truth. When enabled, the `vinkius_state` table
     * (published via the migration) records observed status / connection id /
     * last update per user + connector, for UI rendering without an API call.
     */
    'state_cache' => [
        'enabled' => false,
        'table' => 'vinkius_state',
    ],

    /*
     * Observability. Secrets are always redacted before reaching these logs.
     */
    'observability' => [
        'log_on_request' => false,
        'log_on_response' => false,
        'log_channel' => null,
    ],
];
