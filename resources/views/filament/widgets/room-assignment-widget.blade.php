<x-filament::widget>
    <x-filament::card>
        <h2 class="text-xl font-bold mb-4">ملخص توزيع المراقبين</h2>

        <div class="space-y-2">
            <p>🔹 عدد القاعات الكبيرة: <strong>{{ $bigRooms }}</strong></p>
            <p>🔹 عدد القاعات الصغيرة: <strong>{{ $smallRooms }}</strong></p>
        </div>

        <hr class="my-3">

        <div class="grid grid-cols-3 gap-4 text-center font-bold">
            <div>النوع</div>
            <div>المطلوب</div>
            <div>الناقص</div>
        </div>

        @foreach ($required as $role => $count)
        <div class="grid grid-cols-3 gap-4 text-center">
            <div>{{ $role }}</div>
            <div>{{ $count }}</div>
            <div class="{{ $shortage[$role] > 0 ? 'text-red-600' : 'text-green-600' }}">
                {{ $shortage[$role] }}
            </div>
        </div>
        @endforeach
    </x-filament::card>
</x-filament::widget>