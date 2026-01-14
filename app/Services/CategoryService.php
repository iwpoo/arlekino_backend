<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Question;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class CategoryService
{
    public function getCategoryTree(int $limit = 20, string $search = ''): LengthAwarePaginator
    {
        $cacheKey = "categories_tree_limit_{$limit}_search_" . md5($search);

        return Cache::remember($cacheKey, 3600, function () use ($limit, $search) {
            return Category::query()
                ->whereNull('parent_id')
                ->when(!empty($search), fn($q) => $q->where('name', 'like', "%$search%"))
                ->with([
                    'questions',
                    'allChildren'
                ])
                ->paginate($limit);
        });
    }

    public function getQuestionsByCategory(int $categoryId, int $perPage = 20): LengthAwarePaginator
    {
        return Question::where('category_id', $categoryId)
            ->latest()
            ->paginate($perPage);
    }

    public function getSubcategories(int $parentId, int $limit = 20, string $search = ''): LengthAwarePaginator
    {
        $cacheKey = "subcategories_{$parentId}_limit_{$limit}_search_" . md5($search);

        return Cache::remember($cacheKey, 3600, function () use ($parentId, $limit, $search) {
            return Category::query()
                ->where('parent_id', $parentId)
                ->when(!empty($search), fn($q) => $q->where('name', 'like', "%$search%"))
                ->with(['questions', 'children'])
                ->paginate($limit);
        });
    }
}
