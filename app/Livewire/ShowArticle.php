<?php

namespace App\Livewire;

use App\Models\Article;
use Livewire\Component;

class ShowArticle extends Component
{
    public $article;

    public function mount($slug)
    {
        $this->article = Article::published()->where('article_slug', $slug)->firstOrFail();
    }

    public function backToArticles()
    {
        // هنا يمكنك تنفيذ منطق معين لإعادة المستخدم إلى قائمة المقالات مثلاً
        $this->emit('showArticlesList');
    }

    public function render()
    {
        return view('livewire.show-article', [
            'article' => $this->article,
            'meta_title' => $this->article->article_meta_title,
            'meta_description' => $this->article->article_meta_description,
            'meta_keywords' => $this->article->article_meta_keywords,
        ]);
    }
}
