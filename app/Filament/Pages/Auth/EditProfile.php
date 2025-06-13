<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;

class EditProfile extends BaseEditProfile
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getNameFormComponent(),
                TextInput::make('email')
                    ->label('الجوال')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->placeholder('09********'),
                Select::make('month_part')
                    ->label('التوفر في')
                    ->options([
                        'first_half' => 'النصف الأول فقط (1-15)',
                        'second_half' => 'النصف الثاني فقط (16-31)',
                        'any' => 'أي يوم في الشهر',
                    ])
                    ->required()
                    ->default('any'),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }
}
