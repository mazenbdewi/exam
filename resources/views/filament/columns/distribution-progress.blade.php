@php
$color = $percentage < 50 ? 'bg-red-500' : ($percentage < 100 ? 'bg-yellow-500' : 'bg-green-500' ); @endphp <div
    class="space-y-1">
    <div class="flex justify-between text-xs font-medium">
        <div>مُوزّع: {{ $allocated }}</div>
        <div>غير موزّع: {{ $unallocated }}</div>
    </div>

    <div class="w-full bg-gray-200 rounded-full h-2.5">
        <div class="h-2.5 rounded-full {{ $color }}" style="width: {{ $percentage }}%"></div>
    </div>

    <div class="text-xs text-gray-500">
        {{ $percentage }}% من الطلاب موزعين
    </div>
    </div>