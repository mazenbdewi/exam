<?php

namespace App\Livewire;

use App\Models\Setting;
use Livewire\Component;

class HomePage extends Component
{
    public function render()
    {
        $setting = Setting::where('setting_id', 1)->first();

        return view('livewire.home-page', compact('setting'));
    }
}
