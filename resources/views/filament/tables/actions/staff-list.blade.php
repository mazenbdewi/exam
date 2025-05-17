<div class="space-y-6">
    @foreach($groupedStaff as $role => $staffGroup)
    <div class="border rounded-lg p-4">
        <h3 class="font-bold mb-2 text-lg">
            @switch($role)
            @case('رئيس_قاعة') رئيس القاعة @break
            @case('امين_سر') أمناء السر @break
            @case('مراقب') المراقبون @break
            @default دور غير محدد
            @endswitch
            <span class="text-gray-500 text-sm">({{ $staffGroup->count() }})</span>
        </h3>

        <div class="space-y-2">
            @foreach($staffGroup as $observer)
            <div class="flex items-center gap-3 p-2 bg-gray-50 rounded">
                <x-heroicon-o-user class="w-5 h-5 text-primary-600" />
                <span>{{ $observer->user->name }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach
</div>