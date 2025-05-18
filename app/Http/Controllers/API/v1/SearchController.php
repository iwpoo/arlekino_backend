<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    const SEARCH_TYPES = ['products', 'posts'];
    const DEFAULT_PER_PAGE = 20;
    const CACHE_TTL_HOURS = 6;
    const CACHE_TTL_SHORT = 60; // 1 minute for frequently changing data

    public function search(Request $request)
    {
        $query = trim($request->input('query', ''));
        $page = (int)$request->input('page', 1);
        $type = $request->input('type', 'products');

        if (!in_array($type, self::SEARCH_TYPES)) {
            $type = 'products';
        }

        $perPage = (int)$request->input('per_page', self::DEFAULT_PER_PAGE);
        $perPage = min($perPage, 100); // Limit to 100 items per page

        $cacheKey = $this->generateCacheKey($type, $query, $page, $perPage);

        $results = Cache::remember($cacheKey, now()->addHours(self::CACHE_TTL_HOURS), function() use ($type, $query, $perPage) {
            return $this->performSearch($query, $perPage, $type);
        });

        return response()->json([
            'results' => $results,
            'suggestions' => $this->getSearchSuggestions($query),
            'filters' => $this->getAvailableFilters($type, $query),
            'meta' => [
                'total' => $results->total(),
                'current_page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'last_page' => $results->lastPage(),
            ]
        ]);
    }

    protected function generateCacheKey(string $type, string $query, int $page, int $perPage): string
    {
        return sprintf('search_%s_%s_page_%d_per_%d',
            $type,
            md5($query),
            $page,
            $perPage
        );
    }

    protected function performSearch(string $query, int $perPage, string $type = 'products')
    {
        $keywords = $this->extractKeywords($query);

        if ($type === 'posts') {
            return $this->searchPosts($keywords, $perPage);
        }

        return $this->searchProducts($keywords, $perPage);
    }

    protected function extractKeywords(string $query): array
    {
        $keywords = explode(' ', preg_replace('/\s+/', ' ', trim($query)));
        return array_filter($keywords, fn($word) => strlen($word) > 1);
    }

    protected function searchProducts(array $keywords, int $perPage)
    {
        $query = Product::with(['files', 'category']);

        if (empty($keywords)) {
            return $query->orderBy('views_count', 'desc')
                ->paginate($perPage);
        }

        if (config('database.default') === 'mysql') {
            return $this->fullTextSearch($query, $keywords, 'products', $perPage);
        }

        return $this->likeSearch($query, $keywords, ['title', 'content'], $perPage);
    }

    protected function searchPosts(array $keywords, int $perPage)
    {
        $query = Post::with(['files', 'user'])
            ->where('is_published', true);

        if (empty($keywords)) {
            return $query->orderBy('views_count', 'desc')
                ->paginate($perPage);
        }

        if (config('database.default') === 'mysql') {
            return $this->fullTextSearch($query, $keywords, 'posts', $perPage);
        }

        return $this->likeSearch($query, $keywords, ['content'], $perPage);
    }

    protected function fullTextSearch($query, array $keywords, string $table, int $perPage)
    {
        $searchTerm = implode(' ', $keywords);
        $columns = $table === 'products' ? 'title, content' : 'content';

        return $query->select('*')
            ->selectRaw(
                "MATCH({$columns}) AGAINST(? IN BOOLEAN MODE) as relevance",
                [$searchTerm]
            )
            ->whereRaw(
                "MATCH({$columns}) AGAINST(? IN BOOLEAN MODE)",
                [$searchTerm]
            )
            ->orderByDesc('relevance')
            ->orderByDesc('views_count')
            ->paginate($perPage);
    }

    protected function likeSearch($query, array $keywords, array $columns, int $perPage)
    {
        foreach ($keywords as $keyword) {
            $query->where(function($q) use ($keyword, $columns) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', "%{$keyword}%");
                }
            });
        }

        return $query->orderByDesc('views_count')
            ->paginate($perPage);
    }

    protected function getSearchSuggestions(string $query, ?string $type = null)
    {
        if (strlen($query) < 2) {
            return [];
        }

        $cacheKey = 'search_suggestions_' . ($type ? $type . '_' : '') . md5($query);

        return Cache::remember($cacheKey, now()->addHours(self::CACHE_TTL_SHORT), function() use ($query, $type) {
            if ($type === 'products') {
                return Product::query()
                    ->select('title as text', DB::raw("'product' as type"))
                    ->where('title', 'like', $query . '%')
                    ->groupBy('title')
                    ->orderByRaw('COUNT(*) DESC')
                    ->limit(5)
                    ->get()
                    ->toArray();
            }

            if ($type === 'posts') {
                return Post::query()
                    ->select(DB::raw("SUBSTRING(content, 1, 50) as text"), DB::raw("'post' as type"))
                    ->where('content', 'like', $query . '%')
                    ->where('is_published', true)
                    ->groupBy('text')
                    ->orderByRaw('COUNT(*) DESC')
                    ->limit(5)
                    ->get()
                    ->toArray();
            }

            $productSuggestions = Product::query()
                ->select('title as text', DB::raw("'product' as type"))
                ->where('title', 'like', $query . '%')
                ->groupBy('title')
                ->orderByRaw('COUNT(*) DESC')
                ->limit(3)
                ->get()
                ->toArray();

            $postSuggestions = Post::query()
                ->select(DB::raw("SUBSTRING(content, 1, 50) as text"), DB::raw("'post' as type"))
                ->where('content', 'like', $query . '%')
                ->where('is_published', true)
                ->groupBy('text')
                ->orderByRaw('COUNT(*) DESC')
                ->limit(2)
                ->get()
                ->toArray();

            $allSuggestions = array_merge($productSuggestions, $postSuggestions);
            shuffle($allSuggestions);
            return array_slice($allSuggestions, 0, 5);
        });
    }

    protected function getAvailableFilters(string $type, string $query)
    {
        $cacheKey = 'search_filters_' . $type . '_' . md5($query);

        return Cache::remember($cacheKey, now()->addHours(self::CACHE_TTL_HOURS), function() use ($type, $query) {
            if ($type === 'posts') {
                return $this->getPostFilters($query);
            }

            return $this->getProductFilters($query);
        });
    }

    protected function getProductFilters(string $query)
    {
        $keywords = $this->extractKeywords($query);
        $query = Product::query();

        foreach ($keywords as $keyword) {
            $query->where(function($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('content', 'like', "%{$keyword}%");
            });
        }

        return [
            'price_range' => [
                'min' => $query->min('price'),
                'max' => $query->max('price'),
            ],
            'categories' => $query->join('categories', 'products.category_id', '=', 'categories.id')
                ->select('categories.id', 'categories.name', DB::raw('COUNT(*) as count'))
                ->groupBy('categories.id', 'categories.name')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
        ];
    }

    protected function getPostFilters(string $query)
    {
        $keywords = $this->extractKeywords($query);
        $baseQuery = Post::query()->where('is_published', true);

        foreach ($keywords as $keyword) {
            $baseQuery->where('content', 'like', "%{$keyword}%");
        }

        $dateRange = $baseQuery->clone()
            ->select([
                DB::raw('MIN(created_at) as min_date'),
                DB::raw('MAX(created_at) as max_date')
            ])
            ->first();

        $popularAuthors = $baseQuery->clone()
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->select([
                'users.id',
                'users.name',
                DB::raw('COUNT(*) as count')
            ])
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        return [
            'popular_authors' => $popularAuthors,
            'date_range' => [
                'min' => $dateRange->min_date,
                'max' => $dateRange->max_date,
            ],
        ];
    }
}
