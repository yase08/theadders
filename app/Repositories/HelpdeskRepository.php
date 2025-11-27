<?php

namespace App\Repositories;

use App\Interfaces\HelpdeskInterface;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskArticle;
use App\Models\HelpdeskNavigation;

class HelpdeskRepository implements HelpdeskInterface
{
    public function getAllCategories()
    {
        return HelpdeskCategory::where('is_active', 1)->orderBy('sort_order', 'asc')->get();
    }

    public function getCategoryById($id)
    {
        return HelpdeskCategory::where('is_active', 1)->find($id);
    }

    public function getAllArticles()
    {
        return HelpdeskArticle::where('is_active', 1)->with('category')->get();
    }

    public function getArticleById($id)
    {
        return HelpdeskArticle::where('is_active', 1)->with('category')->find($id);
    }

    public function getArticlesByCategory($categoryId)
    {
        return HelpdeskArticle::where('is_active', 1)
            ->where('category_id', $categoryId)
            ->with('category')
            ->get();
    }

    public function getAllNavigation()
    {
        return HelpdeskNavigation::where('is_active', 1)->orderBy('sort_order', 'asc')->get();
    }
}
