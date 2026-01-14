<?php

namespace App\Services;

use App\Jobs\RecordInteractionJob;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecommendationService
{
    const FEED_CACHE_TTL = 600;
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getHybridFeed(User $user, int $limit = 20): array
    {
        $cacheKey = $this->generateFeedCacheKey('hybrid', $user, $limit);

        return Cache::remember($cacheKey, self::FEED_CACHE_TTL, function() use ($user, $limit) {
            $subLimit = (int)($limit * 0.7);

            $subscriptionPosts = $this->getSubscriptionPosts($user, $subLimit);
            $subscriptionProducts = $this->getSubscriptionProducts($user, $subLimit);

            $discovery = $this->getDiscoveryFeed($user, $limit);

            return [
                'posts' => $this->interleaveCollections($subscriptionPosts, collect($discovery['posts']), $limit),
                'products' => $this->interleaveCollections($subscriptionProducts, collect($discovery['products']), $limit),
                'has_recommendations' => !empty($discovery['posts'])
            ];
        });
    }

    public function getDiscoveryFeed(User $user, int $limit = 20): array
    {
        $params = [
            'index' => 'search_index',
            'body'  => [
                'size' => $limit,
                'query' => [
                    'function_score' => [
                        'query' => [
                            'bool' => [
                                'must_not' => [
                                    ['term' => ['user_id' => $user->id]],
                                ]
                            ]
                        ],
                        'functions' => [
                            ['field_value_factor' => [
                                'field' => 'popularity',
                                'factor' => 1.2,
                                'modifier' => 'log1p',
                                'missing' => 1
                            ]],
                            ['gauss' => [
                                'created_at' => [
                                    'origin' => 'now',
                                    'scale'  => '7d',
                                    'offset' => '1d',
                                    'decay'  => 0.5
                                ]
                            ]]
                        ],
                        'score_mode' => 'multiply'
                    ]
                ]
            ]
        ];

        try {
            $response = $this->client->search($params);
            return $this->formatElasticResponse($response);
        } catch (ClientResponseException $e) {
            Log::error("Elasticsearch Client Error (4xx): " . $e->getMessage(), [
                'user_id' => $user->id,
                'params' => $params
            ]);
        } catch (ServerResponseException $e) {
            Log::emergency("Elasticsearch Server Error (5xx): " . $e->getMessage());
        } catch (Throwable $e) {
            Log::critical("Unexpected Search Error: " . $e->getMessage());
        }

        return [
            'items' => [],
            'total' => 0,
            'error' => true
        ];
    }

    private function getSubscriptionPosts(User $user, int $limit): Collection
    {
        return Post::whereIn('user_id', function ($query) use ($user) {
            $query->select('following_id')->from('follows')->where('follower_id', $user->id);
        })
            ->where('is_published', true)
            ->with(['user:id,name,username,avatar_path', 'files:id,post_id,file_path,file_type'])
            ->latest()
            ->limit($limit)
            ->get()
            ->each(fn($post) => $post->recommendation_score = 1000);
    }

    private function getSubscriptionProducts(User $user, int $limit): \Illuminate\Support\Collection
    {
        return Product::whereIn('user_id', function ($query) use ($user) {
            $query->select('following_id')->from('follows')->where('follower_id', $user->id);
        })
            ->with([
                'user:id,name,username,city,region_id,avatar_path',
                'files:id,product_id,file_path,file_type',
                'promotions'
            ])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->each(function ($product) {
                $bestPromotion = $product->getBestPromotion();
                if ($bestPromotion) {
                    $product->best_promotion = $bestPromotion;
                }
                $product->recommendation_score = 1000;
            });
    }

    private function interleaveCollections($primary, $secondary, int $limit): \Illuminate\Support\Collection
    {
        $result = collect();
        $primary = $primary->values();
        $secondary = $secondary->values();
        $i = $j = 0;

        while ($result->count() < $limit && ($i < $primary->count() || $j < $secondary->count())) {
            if ($i < $primary->count()) $result->push($primary[$i++]);
            if ($result->count() >= $limit) break;
            if ($j < $secondary->count()) $result->push($secondary[$j++]);
        }

        return $result;
    }

    public function recordInteraction(User $user, $target, string $interactionType, int $weight = 1): void
    {
        RecordInteractionJob::dispatch(
            $user->id,
            $target->id,
            $target->getTable(),
            $interactionType,
            $weight
        );
    }

    private function formatElasticResponse($response): array
    {
        $hits = collect($response['hits']['hits']);
        if ($hits->isEmpty()) return ['posts' => [], 'products' => []];

        $postIds = $hits->where('_source.entity_type', 'posts')->pluck('_source.id')->toArray();
        $productIds = $hits->where('_source.entity_type', 'products')->pluck('_source.id')->toArray();

        $posts = Post::with(['user', 'files'])->whereIn('id', $postIds)
            ->get()->keyBy('id');

        $products = Product::with(['user', 'files', 'promotions'])->whereIn('id', $productIds)
            ->get()->keyBy('id');

        $orderedPosts = [];
        $orderedProducts = [];

        foreach ($hits as $hit) {
            $id = $hit['_source']['id'];
            $type = $hit['_source']['entity_type'];
            $score = $hit['_score'];

            if ($type === 'posts' && isset($posts[$id])) {
                $model = $posts[$id];
                $model->recommendation_score = $score;
                $orderedPosts[] = $model;
            } elseif ($type === 'products' && isset($products[$id])) {
                $model = $products[$id];
                $model->recommendation_score = $score;
                $orderedProducts[] = $model;
            }
        }

        return [
            'posts' => $orderedPosts,
            'products' => $orderedProducts
        ];
    }

    private function generateFeedCacheKey(string $type, User $user, int $limit): string
    {
        return "rec_{$type}_{$user->id}_{$limit}_v1";
    }
}
