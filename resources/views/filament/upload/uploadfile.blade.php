<div>
    <x-filament::breadcrumbs :breadcrumbs="[
    '/adminpanel/universities'=>'الطلاب',
    '/adminpanel/universities#'=>'تحميل ملف إكسل'
    ]" />

    <div>
        <form wire:submit="save" class="w-full max-w-sm flex mt-2">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="fileInput">
                    يمكنك رفع ملف الاكسل من هنا
                </label>
                <input
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="fileInput" type="file" wire:model="file">
            </div>
            <div class="flex items-center justify-between mt-3">
                <button class="bg-orange-500 hover:bg-orange-700 text-white font-bold py-2 px-4  focus:shadow-outline"
                    style="background-color: #b43232" type="submit">
                    ارسل
                </button>
                <x-filament::loading-indicator wire:loading class="h-5 w-5 ml-2" />


            </div>
        </form>
    </div>
</div>