<?php

namespace App\Livewire;

use App\Models\Article;
use Livewire\Component;

class ArticleDetail extends Component
{
    public $article;

    public function mount($slug)
    {

        $this->article = Article::published()->where('article_slug', $slug)->firstOrFail();

    }

    public function render()
    {

        return view('livewire.article-detail', ['article' => $this->article]);

    }
}
