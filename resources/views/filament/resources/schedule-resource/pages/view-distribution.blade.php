// في ViewDistribution.blade.php
<div>
    <x-filament::card>
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">توزيع امتحان: {{ $record->schedule_subject }}</h2>
            <div class="flex gap-4">
                <div class="px-3 py-1 bg-gray-100 rounded-lg">
                    <span class="font-medium">عدد الطلاب:</span>
                    <span>{{ $record->student_count }}</span>
                </div>
                <div class="px-3 py-1 bg-gray-100 rounded-lg">
                    <span class="font-medium">المتبقي:</span>
                    <span class="{{ $record->remaining_capacity > 0 ? 'text-red-500' : 'text-green-500' }}">
                        {{ $record->remaining_capacity }}
                    </span>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            @foreach($reservations as $reservation)
            <div class="border rounded-lg p-4">
                <div class="flex justify-between">
                    <div>
                        <h3 class="font-medium">{{ $reservation->room->room_name }}</h3>
                        <p class="text-sm text-gray-500">
                            السعة: {{ $reservation->used_capacity }} / {{ $reservation->room->room_capacity_total }}
                            ({{ number_format($reservation->used_capacity/$reservation->room->room_capacity_total*100, 0) }}%)
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                            {{ $reservation->used_capacity }} طلاب
                        </span>
                    </div>
                </div>
            </div>
            @endforeach

            @if($record->remaining_capacity > 0)
            <div class="text-center py-4 border-t">
                <p class="text-red-500">تحذير: يوجد {{ $record->remaining_capacity }} طالب لم يتم توزيعهم</p>
                <button class="mt-2 px-4 py-2 bg-primary-500 text-white rounded-lg">
                    إضافة قاعات إضافية
                </button>
            </div>
            @endif
        </div>
    </x-filament::card>
</div>