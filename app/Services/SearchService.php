<?php

namespace App\Services;

use App\Models\UserBlock;
use Elastic\Elasticsearch\Client;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SearchService
{
    private const DEFAULT_PER_PAGE = 20;

    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function performSearch(Request $request): array
    {
        $query = trim($request->input('query', ''));
        $page = (int)$request->input('page', 1);
        $type = $request->input('type', 'all');
        $perPage = min((int)$request->input('per_page', self::DEFAULT_PER_PAGE), 100);
        $userId = auth()->id();

        $params = [
            'index' => 'search_index',
            'body'  => [
                'from' => ($page - 1) * $perPage,
                'size' => $perPage,
                'query' => $this->buildElasticQuery($query, $type, $userId, $request),
                'sort' => $this->buildSort($request),
                'aggs' => $this->buildAggregations($type),
                'suggest' => [
                    'text' => $query,
                    'simple_phrase' => [
                        'phrase' => [
                            'field' => 'title',
                            'size' => 1,
                            'real_word_error_likelihood' => 0.95,
                            'max_errors' => 2,
                            'direct_generator' => [
                                ['field' => 'title', 'suggest_mode' => 'always']
                            ]
                        ]
                    ]
                ]
            ]
        ];

        try {
            $response = $this->client->search($params);
            return $this->formatResponse($response, $page, $perPage);
        } catch (Exception $e) {
            Log::error("Elasticsearch error: " . $e->getMessage());
            return ['error' => 'Search service temporarily unavailable', 'results' => []];
        }
    }

    private function buildElasticQuery(string $query, string $type, ?int $userId, Request $request): array
    {
        $must = [];
        if (!empty($query)) {
            $must[] = [
                'multi_match' => [
                    'query' => $query,
                    'fields' => ['title^5', 'content', 'name^3', 'username'],
                    'fuzziness' => 'AUTO',
                    'operator' => 'and'
                ]
            ];
        } else {
            $must[] = ['match_all' => (object)[]];
        }

        $filter = [];
        if ($type !== 'all') {
            $filter[] = ['term' => ['entity_type' => $type === 'shops' || $type === 'customers' ? 'users' : $type]];
            if ($type === 'shops') $filter[] = ['term' => ['role' => 'seller']];
            if ($type === 'customers') $filter[] = ['term' => ['role' => 'client']];
        }

        if ($userId) {
            $blockedIds = Cache::remember("user_blocks_$userId", 3600, function() use ($userId) {
                return UserBlock::where('blocker_id', $userId)->pluck('blocked_id')->toArray();
            });

            if (!empty($blockedIds)) {
                $filter[] = ['bool' => ['must_not' => [['terms' => ['user_id' => $blockedIds]]]]];
            }
        }

        if ($request->has('min_price')) $filter[] = ['range' => ['price' => ['gte' => $request->min_price]]];
        if ($request->has('category_id')) $filter[] = ['term' => ['category_id' => $request->category_id]];

        return ['bool' => ['must' => $must, 'filter' => $filter]];
    }

    private function buildAggregations(string $type): array
    {
        $aggs = [
            'types' => ['terms' => ['field' => 'entity_type']]
        ];

        if ($type === 'products' || $type === 'all') {
            $aggs['min_price'] = ['min' => ['field' => 'price']];
            $aggs['max_price'] = ['max' => ['field' => 'price']];
            $aggs['categories'] = ['terms' => ['field' => 'category_id', 'size' => 50]];
        }

        return $aggs;
    }

    private function formatResponse($response, $page, $perPage): array
    {
        $hits = $response['hits']['hits'];
        $total = $response['hits']['total']['value'];

        $results = array_map(function ($hit) {
            return [
                'type' => $hit['_source']['entity_type'],
                'relevance' => $hit['_score'],
                'entity' => $hit['_source'],
            ];
        }, $hits);

        $suggestion = $response['suggest']['simple_phrase'][0]['options'][0]['text'] ?? null;

        return [
            'results' => new LengthAwarePaginator($results, $total, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]),
            'did_you_mean' => $suggestion,
            'filters' => $this->parseAggregations($response['aggregations'] ?? []),
            'meta' => [
                'total' => $total,
                'took' => $response['took']
            ]
        ];
    }

    private function parseAggregations(array $aggs): array
    {
        return [
            'price_range' => [
                'min' => $aggs['min_price']['value'] ?? 0,
                'max' => $aggs['max_price']['value'] ?? 0,
            ],
            'categories' => collect($aggs['categories']['buckets'] ?? [])->map(fn($b) => ['id' => $b['key'], 'count' => $b['doc_count']]),
            'counts' => collect($aggs['types']['buckets'] ?? [])->pluck('doc_count', 'key')
        ];
    }

    private function buildSort(Request $request): array
    {
        if ($request->sort === 'newest') return ['created_at' => 'desc'];
        if ($request->sort === 'price_low') return ['price' => 'asc'];
        if ($request->sort === 'popularity') return ['popularity' => 'desc'];

        return ['_score' => 'desc'];
    }
}
