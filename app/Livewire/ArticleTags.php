<?php

namespace App\Livewire;

use App\Models\Article;
use Livewire\Component;

class ArticleTags extends Component
{
    public $articleId;

    public $tags;

    public function mount($articleId)
    {
        $this->articleId = $articleId;
        $this->tags = Article::findOrFail($this->articleId)->tags;
    }

    public function render()
    {
        return view('livewire.article-tags');
    }
}
