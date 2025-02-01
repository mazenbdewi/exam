<?php

namespace App\Livewire;

use App\Models\Article;
use App\Models\Section;
use Livewire\Component;

class HomePageSections extends Component
{
    public function render()
    {
        $sections = Section::with(['page'])
            ->where('section_active', true)
            ->get();

        // تحقق من وجود بيانات قبل جلب المقالات
        if ($sections->isNotEmpty()) {
            foreach ($sections as $section) {
                $section->articles = Article::published()->where('page_id', $section->page_id)
                    ->limit($section->section_article_limit)
                    ->get();
            }
        }

        return view('livewire.home-page-sections', compact('sections'));
    }
}
