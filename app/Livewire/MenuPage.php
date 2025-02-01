<?php

namespace App\Livewire;

use App\Models\Menu;
use App\Models\Setting;
use Livewire\Component;

class MenuPage extends Component
{
    public function render()
    {
        $setting = Setting::where('setting_id', 1)->first();

        $menus = Menu::where('menu_active', '=', 1)
            ->whereNull('parent_id')
            ->with(['children' => function ($query) {
                $query->where('menu_active', '=', 1)
                    ->whereHas('page', function ($query) {
                        $query->where('page_active', '=', 1);
                    })
                    ->with('page')
                    ->orderBy('menu_order', 'ASC');
            }, 'page' => function ($query) {
                $query->where('page_active', '=', 1);
            }])
            ->orderBy('menu_order', 'ASC')
            ->get();

        return view('livewire.menu-page', compact('menus', 'setting'));
    }
}
