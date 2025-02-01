<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ArticlesList extends Component
{
    public $articles;

    public $paginate;

    /**
     * Create a new component instance.
     */
    public function __construct($articles, $paginate = true)
    {
        $this->articles = $articles;
        $this->paginate = $paginate;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.articles-list');
    }
}
