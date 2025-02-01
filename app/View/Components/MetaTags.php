<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class MetaTags extends Component
{
    public $title;

    public $description;

    public $keywords;

    /**
     * Create a new component instance.
     */
    public function __construct($title = null, $description = null, $keywords = null)
    {
        $this->title = $title;
        $this->description = $description;
        $this->keywords = $keywords;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.meta-tags');
    }
}
