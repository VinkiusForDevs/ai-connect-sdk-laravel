<?php

namespace Vinkius\ConnectLaravel;

use Vinkius\ConnectLaravel\Exceptions\NotFoundException;

class CapabilitySet
{
    /** @var list<Capability> */
    private array $items;

    /**
     * @param  list<Capability>  $capabilities
     */
    public function __construct(array $capabilities = [])
    {
        $this->items = $capabilities;
    }

    /**
     * @param  list<Capability>  $capabilities
     */
    public static function fromCapabilities(array $capabilities): self
    {
        return new self($capabilities);
    }

    /**
     * @param  list<Capability>  $capabilities
     */
    public function push(...$capabilities): void
    {
        foreach ($capabilities as $capability) {
            $this->items[] = $capability;
        }
    }

    /**
     * @return list<Capability>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function forConnector(string $slug): self
    {
        return self::fromCapabilities(
            array_values(array_filter($this->items, static fn (Capability $c) => $c->routing['connector'] === $slug))
        );
    }

    public function find(string $name): ?Capability
    {
        foreach ($this->items as $capability) {
            if ($capability->name === $name || $capability->rawName === $name) {
                return $capability;
            }
        }

        return null;
    }

    /**
     * Find by display or raw name, throwing a typed error when absent.
     */
    public function findOrFail(string $name): Capability
    {
        $found = $this->find($name);
        if ($found === null) {
            throw new NotFoundException("Unknown capability: {$name}");
        }

        return $found;
    }

    public function merge(self ...$others): self
    {
        $merged = $this->items;
        foreach ($others as $other) {
            $merged = array_merge($merged, $other->items);
        }

        return self::fromCapabilities($merged);
    }

    public function filter(callable $predicate): self
    {
        return self::fromCapabilities(array_values(array_filter($this->items, $predicate)));
    }

    public function map(callable $callback): array
    {
        return array_map($callback, $this->items);
    }

    public function each(callable $callback): void
    {
        foreach ($this->items as $item) {
            $callback($item);
        }
    }

    /**
     * @return list<Capability>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    public function toOpenAITools(): array
    {
        return Adapters\OpenAI::tools($this->items);
    }

    public function toAnthropicTools(): array
    {
        return Adapters\Anthropic::tools($this->items);
    }

    public function toGeminiTools(): array
    {
        return Adapters\Gemini::tools($this->items);
    }

    public function toJSONSchemaTools(): array
    {
        return Adapters\JsonSchema::tools($this->items);
    }
}
