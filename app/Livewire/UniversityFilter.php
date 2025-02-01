<?php

namespace App\Livewire;

use App\Models\University;
use Livewire\Component;
use Livewire\WithPagination;

class UniversityFilter extends Component
{
    use WithPagination;

    public $edu_level;

    public $lang;

    public $unv_type;

    public $city;

    public $major_ar;

    public $loading = false;

    public $searchPerformed = false;

    public $perPage = 10;

    public function updated($name, $value)
    {
        $this->resetPage();
    }

    public function filterUniversities()
    {
        $this->resetPage();
        $this->searchPerformed = true;
        $this->loading = false;
    }

    public function loadMore()
    {

        $this->perPage += 10;

    }

    // public function updatedPage()
    // {
    //     $this->loading = false;
    // }

    public function render()
    {
        $query = University::query();

        if ($this->edu_level) {
            $query->where('edu_level', $this->edu_level);
        }

        if ($this->lang) {
            $query->where('lang', $this->lang);
        }

        if ($this->unv_type) {
            $query->where('unv_type', $this->unv_type);
        }

        if ($this->city) {
            $query->where('city', $this->city);
        }

        if ($this->major_ar) {
            $query->where('major_ar', $this->major_ar);
        }

        $universities = $query->paginate($this->perPage);

        return view('livewire.university-filter', [
            'universities' => $universities,
            'edu_levels' => University::select('edu_level')
                ->whereNotNull('edu_level')
                ->where('edu_level', '!=', '')
                ->distinct()->get(),
            'langs' => University::select('lang')
                ->whereNotNull('lang')
                ->where('lang', '!=', '')
                ->distinct()->get(),
            'unv_types' => University::select('unv_type')
                ->whereNotNull('unv_type')
                ->where('unv_type', '!=', '')
                ->distinct()->get(),
            'cities' => University::select('city')
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->distinct()->get(),
            'majors' => University::select('major_ar')
                ->whereNotNull('major_ar')
                ->where('major_ar', '!=', '')
                ->distinct()->get(),
        ]);
    }
}
