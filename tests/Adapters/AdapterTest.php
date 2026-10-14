<?php

namespace Vinkius\ConnectLaravel\Tests\Adapters;

use PHPUnit\Framework\TestCase;
use Vinkius\ConnectLaravel\Adapters\Anthropic;
use Vinkius\ConnectLaravel\Adapters\Gemini;
use Vinkius\ConnectLaravel\Adapters\JsonSchema;
use Vinkius\ConnectLaravel\Adapters\OpenAI;
use Vinkius\ConnectLaravel\Capability;
use Vinkius\ConnectLaravel\CapabilitySet;
use Vinkius\ConnectLaravel\Exceptions\ConfigurationException;

final class AdapterTest extends TestCase
{
    private CapabilitySet $set;

    protected function setUp(): void
    {
        $capability = new Capability(
            connector: 'github',
            connectionId: 'conn-1',
            name: 'github__create_issue',
            rawName: 'create_issue',
            title: 'Create an issue',
            description: 'Opens a new GitHub issue.',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'owner' => ['type' => 'string'],
                    'repo' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                ],
                'required' => ['owner', 'repo', 'title'],
            ],
            executor: static fn (string $rawName, ?array $args, ?array $opts): array => [
                'content' => [
                    ['type' => 'text', 'text' => "issue opened ({$rawName})"],
                ],
                'isError' => false,
            ],
        );

        $this->set = CapabilitySet::fromCapabilities([$capability]);
    }

    public function test_openai_tool_shape(): void
    {
        $tools = OpenAI::tools($this->set->all());

        self::assertCount(1, $tools);
        self::assertSame('function', $tools[0]['type']);
        self::assertSame('github__create_issue', $tools[0]['function']['name']);
        self::assertSame('object', $tools[0]['function']['parameters']['type']);
    }

    public function test_anthropic_tool_shape(): void
    {
        $tools = Anthropic::tools($this->set->all());

        self::assertSame('github__create_issue', $tools[0]['name']);
        self::assertArrayHasKey('input_schema', $tools[0]);
    }

    public function test_gemini_tool_shape(): void
    {
        $tools = Gemini::tools($this->set->all());

        self::assertSame('github__create_issue', $tools[0]['name']);
        self::assertArrayHasKey('parameters', $tools[0]);
    }

    public function test_json_schema_tool_shape(): void
    {
        $tools = JsonSchema::tools($this->set->all());

        self::assertSame('github__create_issue', $tools[0]['name']);
        self::assertArrayHasKey('parameters', $tools[0]);
    }

    public function test_execute_invokes_executor_with_raw_name_and_args(): void
    {
        $capability = $this->set->findOrFail('github__create_issue');
        $result = $capability->execute(['owner' => 'acme', 'repo' => 'x', 'title' => 'y']);

        self::assertFalse($result['isError']);
        self::assertSame('issue opened (create_issue)', $result['content'][0]['text']);
    }

    public function test_normalise_empty_schema_to_object(): void
    {
        $capability = new Capability(
            connector: 'github',
            connectionId: 'conn-1',
            name: 'github__no_params',
            rawName: 'no_params',
            title: null,
            description: '',
            inputSchema: [],
            executor: static fn (): array => ['content' => [], 'isError' => false],
        );

        $tools = JsonSchema::tools([$capability]);
        self::assertSame(['type' => 'object', 'properties' => []], $tools[0]['parameters']);
    }

    public function test_gemini_rejects_default_namespacing_with_hyphen(): void
    {
        $capability = new Capability(
            connector: 'google-calendar',
            connectionId: 'conn-1',
            name: 'google-calendar__list_events',
            rawName: 'list_events',
            title: null,
            description: '',
            inputSchema: ['type' => 'object'],
            executor: static fn (): array => ['content' => [], 'isError' => false],
        );

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/cannot be exposed as a Gemini/');

        Gemini::tools([$capability]);
    }

    public function test_find_by_display_and_raw_name(): void
    {
        self::assertSame('github__create_issue', $this->set->find('github__create_issue')->name);
        self::assertSame('create_issue', $this->set->find('create_issue')->rawName);
    }
}
