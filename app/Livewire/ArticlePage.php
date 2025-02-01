<?php

namespace App\Livewire;

use App\Models\Article;
use Livewire\Component;

class ArticlePage extends Component
{
    public function render()
    {
        $articles = Article::published()->with(['category', 'author'])->latest()->take(6)->get();

        return view('livewire.article-page', compact('articles'));
    }
}
