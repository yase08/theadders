<?php

namespace App\Interfaces;

interface HelpdeskInterface
{
    public function getAllCategories();
    public function getCategoryById($id);
    public function getAllArticles();
    public function getArticleById($id);
    public function getArticlesByCategory($categoryId);
    public function getAllNavigation();
}
