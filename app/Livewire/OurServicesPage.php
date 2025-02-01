<?php

namespace App\Livewire;

use App\Models\Article;
use Livewire\Component;

class OurServicesPage extends Component
{
    public $articles;

    public function mount()
    {
        $this->articles = Article::whereHas('page', function ($query) {
            $query->where('page_slug', 'خدماتنا');
        })->oldest()->take(6)->get();
    }

    public function render()
    {
        return view('livewire.our-services-page', [
            'articles' => $this->articles,
        ]);
    }
}
