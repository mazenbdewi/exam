<?php

namespace App\Livewire;

use App\Models\Article;
use App\Models\Setting;
use Livewire\Component;

class FooterPage extends Component
{
    public function render()
    {
        $setting = Setting::where('setting_id', 1)->first();
        $latestArticles = Article::published()->latest()->take(5)->get();

        return view('livewire.footer-page', compact('setting', 'latestArticles'));
    }
}
