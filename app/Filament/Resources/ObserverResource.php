<?php

namespace App\Filament\Resources;

use App\Exports\ObserversExport;
use App\Filament\Resources\ObserverResource\Pages;
use App\Models\Observer;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class ObserverResource extends Resource
{
    protected static ?string $model = Observer::class;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationGroup = 'المراقبات';

    protected static ?string $activeNavigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {

        return $form
            ->schema([

                Forms\Components\Select::make('schedule_id')
                    ->label('المادة')
                    ->options(Schedule::all()->pluck('schedule_subject', 'schedule_id'))
                    ->required()
                    ->reactive()
                    ->searchable()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $schedule = Schedule::find($state);
                        if ($schedule) {
                            $set('schedule_exam_date', $schedule->schedule_exam_date);
                            $set('schedule_time_slot', $schedule->schedule_time_slot);
                        } else {
                            $set('schedule_exam_date', null);
                            $set('schedule_time_slot', null);
                        }
                    }),

                // تاريخ الامتحان (عرض فقط)
                Forms\Components\DatePicker::make('schedule_exam_date')
                    ->label('تاريخ الامتحان')
                    ->disabled()
                    ->visible(fn (callable $get) => $get('schedule_id') !== null),

                // الفترة الزمنية (عرض فقط)
                Forms\Components\Select::make('schedule_time_slot')
                    ->label('الفترة')
                    ->options([
                        'morning' => 'صباحية',
                        'night' => 'مسائية',
                    ])
                    ->disabled()
                    ->visible(fn (callable $get) => $get('schedule_id') !== null),

                // حقل اختيار القاعة
                // Forms\Components\Select::make('room_id')
                //     ->label('القاعة')
                //     ->required()
                //     ->options(function (callable $get) {
                //         $schedule = Schedule::find($get('schedule_id'));
                //         if (! $schedule) {
                //             return [];
                //         }

                //         return Room::where('room_type',
                //             $schedule->schedule_time_slot === 'morning' ? 'small' : 'big'
                //         )->pluck('room_name', 'room_id');
                //     })
                //     ->reactive()
                //     ->visible(fn (callable $get) => $get('schedule_id') !== null),
                Forms\Components\Select::make('room_id')
                    ->label('القاعة')
                    ->required()
                    ->options(function (callable $get) {
                        $schedule = Schedule::find($get('schedule_id'));
                        if (! $schedule) {
                            return [];
                        }

                        // تحديد نوع القاعة بناءً على الفترة الزمنية
                        $roomType = $schedule->schedule_time_slot === 'morning' ? 'small' : 'big';

                        // الحصول على جميع القاعات من النوع المطلوب
                        return Room::where('room_type', $roomType)
                            ->pluck('room_name', 'room_id');
                    })
                    ->reactive()
                    ->visible(fn (callable $get) => $get('schedule_id') !== null),

                Forms\Components\Select::make('user_id')
                    ->label('المراقب')
                    ->options(
                        User::whereHas('roles', function ($query) {
                            $query->whereIn('name', ['مراقب', 'امين_سر', 'رئيس_قاعة']);
                        })->pluck('name', 'id')
                    )
                    ->required()
                    ->reactive()
                    ->searchable()
                    ->visible(fn () => auth()->user()->hasRole('super_admin'))
                    ->default(fn () => auth()->user()->hasRole('super_admin') ? null : auth()->user()->id)
                    ->afterStateUpdated(function ($state, callable $set) {
                        $user = User::find($state);
                        if ($user) {
                            $max = $user->getMaxObserversByAge();
                            $current = Observer::where('user_id', $state)->count();
                            if ($current >= $max) {
                                // تعيين قيمة افتراضية بدلاً من null
                                $set('user_id', auth()->user()->id);
                            }
                        }
                    }),

                Forms\Components\Hidden::make('user_id')
                    ->default(auth()->user()->id)
                    ->visible(fn () => ! auth()->user()->hasRole('super_admin'))
                    ->hidden(fn () => auth()->user()->hasRole('super_admin'))
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (auth()->user()->hasAnyRole(['مراقب', 'امين_سر', 'رئيس_قاعة'])) {
                            $user = User::find($state);
                            if ($user) {
                                $max = $user->getMaxObserversByAge();
                                $current = Observer::where('user_id', $state)->count();
                                if ($current >= $max) {
                                    // تعيين قيمة افتراضية بدلاً من null
                                    $set('user_id', auth()->user()->id);
                                }
                            }
                        }
                    }),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('المراقب'),
                Tables\Columns\TextColumn::make('schedule.schedule_subject')->label('المادة'),
                Tables\Columns\TextColumn::make('schedule.schedule_exam_date')->label('تاريخ الامتحان'),
                Tables\Columns\TextColumn::make('schedule.schedule_time_slot')
                    ->label('الفترة')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'morning' => 'صباحية',
                            'night' => 'مسائية',
                            default => $state,
                        };
                    }),
                Tables\Columns\TextColumn::make('room.room_name')->label('القاعة'),
            ])->modifyQueryUsing(function (Builder $query) {
                // إذا كان المستخدم super_admin، لا تطبق أي تصفية
                if (auth()->user()->hasRole('super_admin')) {
                    return $query;
                }

                // إذا لم يكن super_admin، تطبق تصفية لعرض المواد المسجلة فقط باسم المستخدم الحالي
                return $query->where('user_id', auth()->user()->id);
            })
            // ->filters([
            //     SelectFilter::make('user_id')
            //         ->label('المراقب')
            //         ->searchable()
            //         ->options(fn () => User::pluck('name', 'id')->toArray()),
            // ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('المراقب')
                    ->searchable()
                    ->options(
                        User::whereHas('roles', function ($query) {
                            $query->whereIn('name', ['مراقب', 'امين_سر', 'رئيس_قاعة']);
                        })->pluck('name', 'id')
                    ),
            ])
            ->modifyQueryUsing(function ($query) {
                if (in_array(auth()->user()->role, ['مراقب', 'امين_سر', 'رئيس_قاعة'])) {
                    $query->where('user_id', auth()->id()); // تصفية الجدول ليعرض فقط سجلات المستخدم الحالي
                }
            })

            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('distributeObservers')
                    ->label('توزيع المراقبين')
                    ->icon('heroicon-o-users')
                    ->color('primary')
                    ->hidden(fn () => ! auth()->user()->hasRole('super_admin'))
                    ->action(function () {
                        static::distributeObservers();
                    }),
                Tables\Actions\Action::make('export')
                    ->label('تصدير إلى Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($livewire) {
                        // الحصول على الاستعلام الالإعدادات
                        $baseQuery = static::getEloquentQuery()
                            ->with(['user', 'schedule', 'room'])
                            ->when(
                                ! auth()->user()->hasRole('super_admin'),
                                fn ($query) => $query->where('user_id', auth()->id())
                            );

                        // تطبيق الفلترات بشكل صحيح
                        foreach ($livewire->tableFilters as $filterName => $value) {
                            if (! empty($value)) {
                                $filter = $livewire->getTable()->getFilter($filterName);
                                if ($filter) {
                                    $filter->apply(
                                        $baseQuery, // التصحيح هنا: استخدام Eloquent Builder بدلاً من Query Builder
                                        $value,
                                        $livewire->getTable()->getColumn($filterName)
                                    );
                                }
                            }
                        }

                        return Excel::download(
                            new ObserversExport($baseQuery),
                            'observers-'.now()->format('Y-m-d').'.xlsx'
                        );
                    }),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListObservers::route('/'),
            'create' => Pages\CreateObserver::route('/create'),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_DATE_DDMMYYYY,
        ];
    }

    public function map($observer): array
    {
        return [
            // ...
            Date::dateTimeToExcel($observer->schedule->schedule_exam_date),
            // ...
        ];
    }

    // public static function distributeObservers()
    // {
    //     // الحصول على جميع المراقبين الذين لم يختاروا مراقباتهم بعد
    //     $usersWithoutObservers = User::whereDoesntHave('observers')
    //         ->whereHas('roles', function ($query) {
    //             $query->whereIn('name', ['مراقب', 'امين_سر', 'رئيس_قاعة']);
    //         })
    //         ->get();

    //     // الحصول على جميع الجداول التي لم يتم تعيين مراقبين لها بعد
    //     $schedulesWithoutObservers = Schedule::with('rooms')
    //         ->whereDoesntHave('observers')
    //         ->whereHas('rooms') // تأكد من أن الجدول مرتبط بقاعة واحدة على الأقل
    //         ->get();

    //     // متغيرات لتتبع النتيجة
    //     $totalObserversAssigned = 0;
    //     $totalRoomsAssigned = 0;

    //     foreach ($schedulesWithoutObservers as $scheduleKey => $schedule) {
    //         $room = $schedule->rooms()->first(); // الحصول على أول room مرتبط

    //         if (! $room) {
    //             continue; // تخطى هذا الجدول إذا لم يكن مرتبطًا بأي قاعة
    //         }

    //         // تحديد عدد المراقبين بناءً على نوع القاعة
    //         $maxObservers = $room->room_type === 'big' ? 8 : 4;
    //         $maxSecretaries = $room->room_type === 'big' ? 2 : 1;
    //         $maxHeads = 1;

    //         // توزيع المراقبين
    //         $observersAssigned = 0;
    //         $secretariesAssigned = 0;
    //         $headsAssigned = 0;

    //         foreach ($usersWithoutObservers as $userKey => $user) {
    //             $maxObserversByAge = $user->getMaxObserversByAge();
    //             $currentObserversCount = Observer::where('user_id', $user->id)->count();

    //             // إذا تجاوز المستخدم الحد الأقصى، تخطاه
    //             if ($currentObserversCount >= $maxObserversByAge) {
    //                 continue;
    //             }

    //             // توزيع المراقبين بناءً على الدور
    //             if ($user->hasRole('مراقب') && $observersAssigned < $maxObservers) {
    //                 Observer::create([
    //                     'user_id' => $user->id,
    //                     'schedule_id' => $schedule->schedule_id,
    //                     'room_id' => $room->room_id,
    //                 ]);
    //                 $observersAssigned++;
    //                 $totalObserversAssigned++;

    //                 // إزالة المستخدم من القائمة بعد تعيينه
    //                 unset($usersWithoutObservers[$userKey]);
    //             } elseif ($user->hasRole('امين_سر') && $secretariesAssigned < $maxSecretaries) {
    //                 Observer::create([
    //                     'user_id' => $user->id,
    //                     'schedule_id' => $schedule->schedule_id,
    //                     'room_id' => $room->room_id,
    //                 ]);
    //                 $secretariesAssigned++;
    //                 $totalObserversAssigned++;

    //                 // إزالة المستخدم من القائمة بعد تعيينه
    //                 unset($usersWithoutObservers[$userKey]);
    //             } elseif ($user->hasRole('رئيس_قاعة') && $headsAssigned < $maxHeads) {
    //                 Observer::create([
    //                     'user_id' => $user->id,
    //                     'schedule_id' => $schedule->schedule_id,
    //                     'room_id' => $room->room_id,
    //                 ]);
    //                 $headsAssigned++;
    //                 $totalObserversAssigned++;

    //                 // إزالة المستخدم من القائمة بعد تعيينه
    //                 unset($usersWithoutObservers[$userKey]);
    //             }
    //         }

    //         if ($observersAssigned > 0 || $secretariesAssigned > 0 || $headsAssigned > 0) {
    //             $totalRoomsAssigned++;
    //         }

    //         // إعادة تحميل البيانات بعد التعديلات
    //         $usersWithoutObservers = $usersWithoutObservers->values();
    //         $schedulesWithoutObservers = $schedulesWithoutObservers->values();
    //     }

    //     // إظهار رسالة تنبيه بناءً على النتيجة
    //     if ($totalObserversAssigned > 0) {
    //         Notification::make()
    //             ->title('تم التوزيع بنجاح')
    //             ->body('تم توزيع المراقبين على القاعات')
    //             ->success()
    //             ->send();
    //     } else {
    //         Notification::make()
    //             ->title('لم يتم التوزيع')
    //             ->body('لم يتم توزيع أي مراقبين.')
    //             ->warning()
    //             ->send();
    //     }
    // }

    public static function distributeObservers()
    {
        // الحصول على جميع المراقبين الذين لم يختاروا مراقباتهم بعد
        $usersWithoutObservers = User::whereDoesntHave('observers')
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', ['مراقب', 'امين_سر', 'رئيس_قاعة']);
            })
            ->get();

        // الحصول على جميع الجداول التي لم يتم تعيين مراقبين لها بعد
        $schedulesWithoutObservers = Schedule::with('rooms')
            ->whereDoesntHave('observers')
            ->whereHas('rooms') // تأكد من أن الجدول مرتبط بقاعة واحدة على الأقل
            ->get();

        // متغيرات لتتبع النتيجة
        $totalObserversAssigned = 0;
        $totalRoomsAssigned = 0;

        foreach ($schedulesWithoutObservers as $schedule) {
            $room = $schedule->rooms()->first(); // الحصول على أول room مرتبط

            if (! $room) {
                continue; // تخطى هذا الجدول إذا لم يكن مرتبطًا بأي قاعة
            }

            // تحديد عدد المراقبين بناءً على نوع القاعة
            $maxObservers = $room->room_type === 'big' ? 8 : 4;
            $maxSecretaries = $room->room_type === 'big' ? 2 : 1;
            $maxHeads = 1;

            // توزيع المراقبين
            $observersAssigned = 0;
            $secretariesAssigned = 0;
            $headsAssigned = 0;

            foreach ($usersWithoutObservers as $userKey => $user) {
                $maxObserversByAge = $user->getMaxObserversByAge();
                $currentObserversCount = Observer::where('user_id', $user->id)->count();

                // إذا تجاوز المستخدم الحد الأقصى، تخطاه
                if ($currentObserversCount >= $maxObserversByAge) {
                    continue;
                }

                // توزيع المراقبين بناءً على الدور مع الأولوية للرئيس ثم الأمين ثم المراقب
                $role = $user->getRoleNames()->first();
                $assigned = false;

                // الأولوية لرئيس القاعة
                if ($role === 'رئيس_قاعة' && $headsAssigned < $maxHeads) {
                    $assigned = self::assignObserver($user, $schedule, $room);
                    if ($assigned) {
                        $headsAssigned++;
                    }
                }

                // ثم أمين السر
                elseif ($role === 'امين_سر' && $secretariesAssigned < $maxSecretaries) {
                    $assigned = self::assignObserver($user, $schedule, $room);
                    if ($assigned) {
                        $secretariesAssigned++;
                    }
                }

                // ثم المراقبين العاديين
                elseif ($role === 'مراقب' && $observersAssigned < $maxObservers) {
                    $assigned = self::assignObserver($user, $schedule, $room);
                    if ($assigned) {
                        $observersAssigned++;
                    }
                }

                if ($assigned) {
                    $totalObserversAssigned++;
                    unset($usersWithoutObservers[$userKey]);
                }

                // التوقف إذا اكتملت جميع الأدوار
                if ($headsAssigned >= $maxHeads
                    && $secretariesAssigned >= $maxSecretaries
                    && $observersAssigned >= $maxObservers) {
                    break;
                }
            }

            if ($observersAssigned > 0 || $secretariesAssigned > 0 || $headsAssigned > 0) {
                $totalRoomsAssigned++;
            }

            // إعادة تحميل البيانات بعد التعديلات
            $usersWithoutObservers = $usersWithoutObservers->values();
        }

        // إظهار رسالة تنبيه بناءً على النتيجة
        if ($totalObserversAssigned > 0) {
            Notification::make()
                ->title('تم التوزيع بنجاح')
                ->body("تم توزيع $totalObserversAssigned مراقب على $totalRoomsAssigned قاعة")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('لم يتم التوزيع')
                ->body('لم يتم توزيع أي مراقبين.')
                ->warning()
                ->send();
        }
    }

    // باقي الدوال المساعدة تبقى كما هي

    // ---------------------------
    // Helper Methods (Static)
    // ---------------------------

    private static function assignObserver($user, $schedule, $room): bool
    {
        try {
            // التحقق من تعارض الجداول الزمنية
            $conflictingObservers = Observer::where('user_id', $user->id)
                ->whereHas('schedule', function ($query) use ($schedule) {
                    $query->where('schedule_exam_date', $schedule->schedule_exam_date)
                        ->where('schedule_time_slot', $schedule->schedule_time_slot);
                })
                ->exists();
            if ($conflictingObservers) {
                Notification::make()
                    ->title('خطأ')
                    ->body('هذا المراقب معين بالفعل في جدول آخر في نفس الوقت والتاريخ.')
                    ->danger()
                    ->send();

                return false;
            }

            Observer::create([
                'user_id' => $user->id,
                'schedule_id' => $schedule->schedule_id,
                'room_id' => $room->room_id,
            ]);

            return true;
        } catch (\Exception $e) {
            logger()->error("فشل تعيين المراقب: {$e->getMessage()}");

            return false;
        }
    }

    public static function getPluralLabel(): ?string
    {
        return 'جدول المراقبة';
    }

    public static function getLabel(): ?string
    {
        return 'جدول مراقبة';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
