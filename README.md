<div align="center">

# Vinkius Connect for Laravel

### Build AI agents that act for each user.

One SDK for user-specific tools, credentials, permissions, and execution across **11,000+ systems**.

[![Connectors](https://api.vinkius.com/badges/connectors.svg)](https://vinkius.com)
[![AI Capabilities](https://api.vinkius.com/badges/capabilities.svg)](https://vinkius.com)

[Documentation](https://vinkius.com/learn/en/connect-sdk) · [Vinkius Cloud](https://cloud.vinkius.com) · [Site](https://vinkius.com)

</div>

---

<img src="assets/powered_futuristic_fulfillment_factory.webp" />

<br >

AI applications become more useful when they can take action in the systems their users already work with. But shipping those actions means dealing with integrations, OAuth, credential storage, permissions, API changes, and model-specific tool formats.

**Vinkius Connect handles that connectivity layer.** Your application keeps its users and customer experience. Vinkius resolves each user's connected systems and available capabilities, executes actions on their behalf, and returns the result.

<img src="assets/Connectors_Real_Actions.webp" />

```text
Your AI application
        │
        │  Your authenticated user's external_id
        ▼
Vinkius Connect
  identity · connections · credentials · capabilities · execution
        │
        ├── GitHub
        ├── Slack
        ├── Project management
        └── 11,000+ connected systems
```

## Why Vinkius Connect?

- **Built for multi-user AI products.** Each user has their own connections and available capabilities.
- **One consistent interface.** Discover and execute actions without implementing each integration yourself.
- **Credentials stay out of model context.** Connector secrets are write-only through the documented API and are not returned to application code.
- **Framework flexibility.** Adapt capabilities to OpenAI, Anthropic, Gemini, or a neutral JSON Schema format.
- **Managed connectivity.** Vinkius maintains connectors and their evolving APIs.
- **Your product stays yours.** Use your application's own user IDs and keep the customer relationship and user experience in your product.

## How it works

1. **Identify the user.** Pass the stable `external_id` assigned by your application backend — in Laravel, derived from your authenticated user model.
2. **Discover capabilities.** Vinkius resolves the user's ready connectors and the actions they expose.
3. **Provide tools to the model.** Convert those capabilities into the tool format expected by your model or framework.
4. **Execute the selected action.** Send the tool call to Vinkius and return the result to your agent loop.

The `external_id` is an identifier, **not an authentication mechanism**. Only pass an ID that your backend has associated with the currently authenticated user. Never trust a user ID supplied directly by an untrusted client.

<img src="assets/Invisible_Infrastructure_Secure_Connections.webp" />

## Installation

PHP **8.2+** and Laravel **10, 11, or 12** are required. The SDK uses Guzzle as its runtime dependency.

### Install from Packagist

```bash
composer require vinkius/connect-laravel
```

The service provider is auto-discovered; no manual registration is needed.

```bash
# Optional: publish the configuration file
php artisan vendor:publish --provider="Vinkius\ConnectLaravel\VinkiusServiceProvider" --tag=vinkius-config

# Optional: publish the state-cache migration (only when enabling vinkius.state_cache.enabled)
php artisan vendor:publish --provider="Vinkius\ConnectLaravel\VinkiusServiceProvider" --tag=vinkius-migrations
```

## Quick start

Create an AI application in [Vinkius Cloud](https://cloud.vinkius.com), then create an application key. Keep the key on your server. **Never expose it in browser code, mobile clients, or model prompts.**

Set the environment variables:

```env
VINKIUS_APP_ID=vk_app_...
VINKIUS_APP_KEY=vk_app_sk_...
```

Then bridge your user model:

```php
use Vinkius\ConnectLaravel\HasVinkius;

class User extends Authenticatable
{
    use HasVinkius;
}
```

The package adds **no column to `users` and no new users table.** The platform keys every request on `(application_id, external_id)`; `external_id` is derived from the model (default: `$user->getKey()`) or from the `vinkius.external_id` config (a property name, a callable, or `null`).

Discover and execute a capability:

```php
// In a controller, job, or route handler
$user = $request->user();

// Use the ID of the authenticated user from your own backend.
$capabilities = $user->vinkiusCapabilities();

// Execute one capability by its namespaced name.
$result = $capabilities->findOrFail('github__create_issue')
    ->execute([
        'owner'   => 'acme',
        'repo'    => 'product',
        'title'   => 'Ship it',
    ]);

print_r($result['content']);
```

The capability name and input schema depend on the connector and capabilities available to that user. Discover available capabilities before attempting to execute one.

## Use capabilities with an AI model

The usual agent loop is straightforward:

1. Fetch the authenticated user's capabilities.
2. Convert them into the model's tool format.
3. Send the user's request and tools to the model.
4. Execute the returned tool call through Vinkius.
5. Return the result to the model as part of your agent loop.

### Example: OpenAI Chat Completions

```php
use Vinkius\ConnectLaravel\Adapters\OpenAI;
use Vinkius\ConnectLaravel\Exceptions\QuotaExceededException;

public function chat(Request $request)
{
    $user = $request->user();

    // Resolve this ID only after authenticating the user in your backend.
    $caps = $user->vinkiusCapabilities();

    $response = openai()->chat()->completions()->create([
        'model'    => config('services.openai.model'),
        'messages' => [
            ['role' => 'user', 'content' => "Open a GitHub issue titled 'Ship it'"],
        ],
        'tools'    => OpenAI::tools($caps->all()),
    ]);

    $message = $response['choices'][0]['message'];

    if (empty($message['tool_calls'])) {
        return response()->json(['reply' => $message['content'] ?? 'No tool call returned.']);
    }

    foreach ($message['tool_calls'] as $toolCall) {
        $result = OpenAI::run($caps, [
            'function' => [
                'name'      => $toolCall['function']['name'],
                'arguments' => $toolCall['function']['arguments'],
            ],
        ]);
        print_r($result['content']);
    }

    // Production agent loops send each tool result back to the model
    // using the provider's expected format.
}
```

A complete, runnable controller lives in [`docs/example-controller.php`](docs/example-controller.php).

## Connect a user's system

Provision a connector for a user, set its credentials, check its status, and inspect its capabilities:

```php
$user = $request->user();

$github = $user->vinkiusConnector('github');

$github->connect();                                  // get-or-create the connection
$user->vinkiusSetCredentials('github', ['GITHUB_TOKEN' => $token]);

$status = $github->status();
// not_connected, needs_credentials, ready, or disabled

$capabilities = $github->capabilities();
```

The example assumes the application is already configured and `$token` was obtained securely. In a production application, prefer the supported authorization flow for the connector where available, and never ask users to paste secrets into an AI conversation.

Credential values are write-only through the documented interface. The credential schema and whether a value is set can be inspected; secret values cannot be read back.

## Framework adapters

A capability set can be converted to different tool formats. Adapters shape the tools; they do not require the target framework merely to perform the conversion.

| Framework / format | Adapter | Main methods |
| --- | --- | --- |
| OpenAI Chat Completions | `Adapters\OpenAI` | `tools`, `run` |
| Anthropic Messages | `Adapters\Anthropic` | `tools`, `run` |
| Google Gemini | `Adapters\Gemini` | `tools`, `run` |
| Framework-neutral | `Adapters\JsonSchema` | `tools`, `execute` |

Example:

```php
use Vinkius\ConnectLaravel\Adapters;

$caps = $user->vinkiusCapabilities();

$openaiTools    = $user->vinkiusOpenAITools();
$anthropicTools = $user->vinkiusAnthropicTools();
$geminiTools    = $user->vinkiusGeminiTools();
$neutralTools   = $user->vinkiusJsonSchemaTools();
```

### Namespacing

Capability display names are namespaced across connectors with the default `{connector}__{name}` (for example `github__create_issue`). OpenAI accepts 1–64 characters of `[a-zA-Z0-9_-]`, Anthropic accepts 1–128, and Gemini requires `^[a-zA-Z_][a-zA-Z0-9_]*$` (max 64, no hyphens). Names are validated up front so a mismatched name never fails at call time. When Gemini is the primary target, override the namespace in config:

```php
// config/vinkius.php
'namespace' => fn (string $connector, string $name) =>
    substr(preg_replace('/[^a-zA-Z0-9_]/', '_', "{$connector}_{$name}"), 0, 64),
```

## Core concepts

| Concept | Meaning |
| --- | --- |
| **Connector** | An external system a user connects, such as GitHub or Slack. |
| **Capability** | An executable action exposed by a connector, such as listing repositories or creating an issue. |
| **User** | A user identified by your application's `external_id`. Vinkius does not require a separate end-user identity. |
| **Control plane** | Provisioning and state management for users, connectors, credentials, and catalog discovery. |
| **Execution plane** | Runtime capability discovery and execution, per user through a short-lived token. |

### Capability discovery behavior

`vinkiusCapabilities()` resolves the user's ready connectors, with fan-out concurrency capped at eight. A single unresponsive connector does not necessarily fail the whole batch. Use `onConnectorError` to observe individual connector failures. If all connectors fail, the first error is raised.

## Configuration

`config/vinkius.php`:

```php
return [
    'app_id'      => env('VINKIUS_APP_ID'),
    'app_key'     => env('VINKIUS_APP_KEY'),

    'base_url'    => env('VINKIUS_BASE_URL', 'https://api.vinkius.com'),
    'timeout'     => (int) env('VINKIUS_TIMEOUT', 30000),
    'max_retries' => (int) env('VINKIUS_MAX_RETRIES', 2),
    'user_agent'  => env('VINKIUS_USER_AGENT', 'vinkius-connect-laravel'),

    /* external_id resolver: property name | callable | null (= $model->getKey()) */
    'external_id' => null,

    /* capability namespacing | null (= "{connector}__{name}") */
    'namespace'   => null,

    /* optional local mirror of observed state (off by default) */
    'state_cache' => ['enabled' => false, 'table' => 'vinkius_state'],

    /* redacted observability */
    'observability' => [
        'log_on_request'  => false,
        'log_on_response' => false,
        'log_channel'     => null,
    ],
];
```

- **`app_id`** identifies your Vinkius application.
- **`app_key`** is a server-side secret. Keep it out of clients and prompts.
- **`timeout`** sets the request timeout (milliseconds).
- **`max_retries`** controls retries for retry-safe requests.
- **`user_agent`** appends your application's identifier to the SDK user agent.
- **`namespace`** controls how connector and capability names are combined.
- **`observability`** configures observability hooks; sensitive headers and bodies are redacted before hooks run.

For direct, testable composition without the service container, build the client yourself:

```php
use Vinkius\ConnectLaravel\Vinkius;

$vinkius = new Vinkius(
    app_id: 'vk_app_...',
    api_key: 'vk_app_sk_...',
    guzzle: $customGuzzleClient,
    options: [
        'timeoutMs'   => 30000,
        'maxRetries'  => 2,
        'userAgent'   => 'acme-ai/1.0',
        'namespace'   => fn (string $connector, string $name) => "{$connector}__{$name}",
    ],
);
```

### Retries and idempotency

Transient HTTP statuses (`429`, `502`, `503`, `504`) and network errors are retried with full-jitter backoff only for retry-safe requests. Idempotent methods and requests carrying an `idempotency_key` may be retried. Non-idempotent POST requests, such as minting a connection token, are not automatically retried.

For actions that create or modify data in external systems, design your agent loop to account for duplicate requests and uncertain outcomes. Use idempotency support where available and appropriate.

## Low-level API

The fluent API has REST equivalents when you need more direct control:

```php
use Vinkius\ConnectLaravel\Facades\Vinkius;

$connection = Vinkius::user('alice_123')
    ->connector('github')
    ->connect();

Vinkius::user('alice_123')
    ->connector('github')
    ->credentials->set(['GITHUB_TOKEN' => '...']);

$catalog = Vinkius::catalog();

$result = $catalog->search('github');

$connectors = $catalog->list()['data'];
```

## Error handling

SDK exceptions inherit from `VinkiusException` and expose a status, stable error code, and server `requestId` where available.

Common exception types include:

- `ConfigurationException`
- `AuthenticationException`
- `NotFoundException`
- `ValidationException`
- `RateLimitException`
- `QuotaExceededException`
- `OverageException`
- `ConnectorNotConnectedException`
- `ConnectionException`
- `ProtocolException`

```php
use Vinkius\ConnectLaravel\Exceptions\RateLimitException;
use Vinkius\ConnectLaravel\Exceptions\VinkiusException;

try {
    $caps = $user->vinkiusCapabilities();
} catch (RateLimitException $exc) {
    // Inspect retry_after_ms when present.
    print_r($exc->retryAfterMs);
} catch (VinkiusException $exc) {
    // Useful for logs and support; avoid logging secrets or sensitive payloads.
    print_r([$exc->code(), $exc->status(), $exc->requestId()]);
}
```

Capability-level execution failures are returned as results with `isError` set, allowing an agent loop to pass the failure back to the model and recover. Transport, configuration, and protocol failures are raised as exceptions.

## Security practices

- **Keep application keys server-side.** Never ship `vk_app_sk_*` in frontend code, mobile applications, repositories, or prompts.
- **Authenticate users in your application.** Resolve `external_id` from trusted backend state; do not accept arbitrary user IDs from clients.
- **Scope actions to the right user.** Fetch and execute capabilities for the authenticated user's identity only.
- **Keep raw secrets out of prompts.** Connector credentials are write-only through the documented API and are not intended for model context.
- **Treat tool calls as actions.** Apply your product's confirmation, authorization, and policy requirements before executing sensitive or irreversible operations.
- **Protect logs.** Do not log API keys, tokens, credential values, or sensitive customer data.
- **Handle revocation and failures.** Make connection state visible in your product and define how your application responds when a connector is disabled, expired, or unavailable.

Vinkius Connect provides connectivity and execution primitives. Your application remains responsible for authenticating its users, enforcing product-level policy, and deciding when an AI-requested action requires confirmation.

## Development

Clone the repository and run the test suite in Docker (PHP 8.3 + Composer, no local toolchain required):

```bash
make test
# or explicitly:
docker compose build
docker compose run --rm test vendor/bin/phpunit
```

The tests cover the SDK's logic (retry math, redaction, error mapping, capability and adapter shapes, identifier resolution) offline.

## License

[Apache License 2.0](./LICENSE), Copyright Vinkius.
