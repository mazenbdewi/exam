<?php

namespace App\Livewire;

use App\Models\Article;
use Livewire\Component;
use Livewire\WithPagination;

class SearchArticles extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $searchTerm = '';

    public function updatingSearchTerm()
    {
        $this->resetPage(); // إعادة تعيين الصفحة عند تحديث البحث
    }

    public function render()
    {
        // بدء الاستعلام مع تحديد المقالات المنشورة فقط
        $query = Article::published()->with('category');

        // إذا كان هناك مصطلح بحث، قم بتعديل الاستعلام لتضمين الشرط
        if ($this->searchTerm) {
            $query->where(function ($subQuery) {
                $subQuery->where('article_title', 'like', "%{$this->searchTerm}%")
                    ->orWhere('article_longtext', 'like', "%{$this->searchTerm}%");
            });
        }

        // تنفيذ الاستعلام مع التقسيم إلى صفحات
        $articles = $query->paginate(18);

        return view('livewire.search-articles', [
            'articles' => $articles,
        ]);
    }
}
