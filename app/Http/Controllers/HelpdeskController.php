<?php

namespace App\Http\Controllers;

use App\Interfaces\HelpdeskInterface;
use Illuminate\Http\Request;

class HelpdeskController extends Controller
{
    private HelpdeskInterface $helpdeskRepository;

    public function __construct(HelpdeskInterface $helpdeskRepository)
    {
        $this->helpdeskRepository = $helpdeskRepository;
    }

    public function getCategories()
    {
        try {
            $categories = $this->helpdeskRepository->getAllCategories();
            return response()->json([
                'categories' => $categories,
                'message' => 'success'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function getCategory($id)
    {
        try {
            $category = $this->helpdeskRepository->getCategoryById($id);
            if (!$category) {
                return response()->json(['message' => 'Category not found'], 404);
            }
            return response()->json([
                'category' => $category,
                'message' => 'success'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function getArticles()
    {
        try {
            $articles = $this->helpdeskRepository->getAllArticles();
            return response()->json([
                'articles' => $articles,
                'message' => 'success'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function getArticle($id)
    {
        try {
            $article = $this->helpdeskRepository->getArticleById($id);
            if (!$article) {
                return response()->json(['message' => 'Article not found'], 404);
            }
            return response()->json([
                'article' => $article,
                'message' => 'success'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function getArticlesByCategory($categoryId)
    {
        try {
            $articles = $this->helpdeskRepository->getArticlesByCategory($categoryId);
            return response()->json([
                'articles' => $articles,
                'message' => 'success'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function getNavigation()
    {
        try {
            $navigation = $this->helpdeskRepository->getAllNavigation();
            return response()->json([
                'navigation' => $navigation,
                'message' => 'success'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function getWelcome()
    {
        try {
            $welcomeArticles = \App\Models\HelpdeskArticle::whereHas('category', function($query) {
                    $query->where('title', 'Welcome');
                })
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'data' => $welcomeArticles,
                'message' => 'success'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }
}
