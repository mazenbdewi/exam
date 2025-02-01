<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Models\Slide;
use Livewire\Component;

class SlidePage extends Component
{
    public function render()
    {
        $setting = Setting::firstOrCreate([], [
            'slides_enable' => true, // default value
        ]);

        if ($setting->slides_enable) {
            $slides = Slide::where('slide_active', 1)->orderBy('slide_order', 'ASC')->get();
            $slidesCount = $setting->slides_count;
        } else {
            $slides = collect(); // قائمة فارغة
            $slidesCount = 0;
        }

        return view('livewire.slide-page', compact('slides', 'slidesCount'));
    }
}
