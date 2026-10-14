<?php

namespace Vinkius\ConnectLaravel\Resources;

use Vinkius\ConnectLaravel\Http\Client;
use Vinkius\ConnectLaravel\Exceptions\ProtocolException;

class CatalogResource
{
    public function __construct(protected Client $http) {}

    /**
     * List public connectors (page-based, 50 per page).
     *
     * @return array{data: array<int, array<string, mixed>>, meta?: array, links?: array}
     */
    public function list(?int $page = null, array $options = []): array
    {
        $body = $this->http->get('/catalog/mcps', ['page' => $page], $options);

        return $this->paginated($body);
    }

    /**
     * Fetch a single connector by slug, including its credential schema.
     *
     * @return array<string, mixed>
     */
    public function get(string $slug, array $options = []): array
    {
        $body = $this->http->get('/catalog/mcps/' . rawurlencode($slug), [], $options);

        if (is_array($body) && array_key_exists('data', $body)) {
            return is_array($body['data']) ? $body['data'] : [];
        }

        return is_array($body) ? $body : [];
    }

    /**
     * Search using the same endpoint and ranking as the Vinkius marketplace.
     *
     * @return array{items: array<int, array<string, mixed>>, hasMore: bool, page: int}
     */
    public function search(string $query, ?int $page = 1, array $options = []): array
    {
        $body = $this->http->get('/marketplace/search', ['q' => $query, 'page' => $page ?? 1], $options);

        return $this->normalizeMarketplaceSearch($body);
    }

    private function normalizeMarketplaceSearch(mixed $body): array
    {
        if (!is_array($body) || !isset($body['results']) || !is_array($body['results'])) {
            throw new ProtocolException('Malformed marketplace search response: missing results array.', 200, $body);
        }

        $items = [];
        foreach ($body['results'] as $index => $item) {
            $items[] = $this->toSearchConnector($item, $index);
        }

        return [
            'items' => $items,
            'hasMore' => ($body['has_more'] ?? false) === true,
            'page' => isset($body['page']) && is_numeric($body['page']) ? (int) $body['page'] : 1,
        ];
    }

    private function toSearchConnector(mixed $item, int $index): array
    {
        $it = is_array($item) ? $item : (is_object($item) ? (array) $item : []);

        $required = ['id', 'slug', 'title', 'listing_type'];
        foreach ($required as $field) {
            $val = $it[$field] ?? null;
            if (!is_string($val) || trim($val) === '') {
                throw new ProtocolException("Malformed marketplace search item at index {$index}: missing {$field}.", 200, $item);
            }
        }

        $connector = [
            'id' => $it['id'],
            'slug' => $it['slug'],
            'title' => $it['title'],
            'short_description' => isset($it['short_description']) && is_string($it['short_description']) ? $it['short_description'] : null,
            'publisher_type' => isset($it['publisher_type']) && is_string($it['publisher_type']) ? $it['publisher_type'] : 'community',
            'listing_type' => $it['listing_type'],
            'requires_buyer_auth' => ($it['requires_buyer_auth'] ?? false) === true || ($it['requires_auth'] ?? false) === true,
        ];

        if (isset($it['server_type']) && is_string($it['server_type'])) {
            $connector['server_type'] = $it['server_type'];
        }
        if (isset($it['tools_count']) && is_numeric($it['tools_count'])) {
            $connector['tools_count'] = (int) $it['tools_count'];
        }

        return $connector;
    }

    private function paginated(mixed $body): array
    {
        if (!is_array($body)) {
            throw new ProtocolException('Expected a list response envelope.', 200, $body);
        }
        if (!isset($body['data']) || !is_array($body['data'])) {
            throw new ProtocolException('Expected a list response envelope.', 200, $body);
        }

        $out = ['data' => $body['data']];
        if (isset($body['meta']) && is_array($body['meta'])) {
            $out['meta'] = $body['meta'];
        }
        if (isset($body['links']) && is_array($body['links'])) {
            $out['links'] = $body['links'];
        }

        return $out;
    }
}
