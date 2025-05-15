<x-filament::widget>
    <x-filament::card>
        <h2 class="text-lg font-bold mb-4">توزيع الكوادر على القاعات</h2>
        <div class="space-y-4">
            @foreach ($assignments as $assignment)
            <div class="border p-3 rounded-md bg-gray-50">
                <p><strong>القاعة:</strong> {{ $assignment['room_name'] }} ({{ $assignment['room_type'] }})</p>
                <p><strong>التاريخ:</strong> {{ $assignment['date'] }} | <strong>الوقت:</strong>
                    {{ $assignment['time_slot'] }}</p>
                <p><strong>رئيس القاعة:</strong> {{ $assignment['president'] }}</p>
                <p><strong>أمناء السر:</strong> {{ implode(', ', $assignment['secretaries']) }}</p>
                <p><strong>المراقبين:</strong> {{ implode(', ', $assignment['monitors']) }}</p>
            </div>
            @endforeach
        </div>
    </x-filament::card>
</x-filament::widget>