<?php

namespace App\Http\Controllers\API\v1\Product;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function getCategoriesWithQuestions()
    {
        return Cache::remember('categories_with_questions', 3600, function() {
            return Category::with(['questions'])->get();
        });
    }

    public function getQuestionsByCategory($categoryId)
    {
        $questions = Question::where('category_id', $categoryId)->get();
        return response()->json($questions);
    }
}
