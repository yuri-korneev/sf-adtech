<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Кабинет рекламодателя
        </h2>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <div class="grid md:grid-cols-2 gap-4">
            <a href="{{ route('adv.offers.index') }}"
               class="block p-4 bg-white border rounded hover:bg-gray-50">
                <div class="font-semibold mb-1">Мои офферы</div>
                <div class="text-sm text-gray-600">Список и управление офферами</div>
            </a>

            <a href="{{ route('adv.stats') }}"
               class="block p-4 bg-white border rounded hover:bg-gray-50">
                <div class="font-semibold mb-1">Статистика</div>
                <div class="text-sm text-gray-600">Клики и стоимость по дням</div>
            </a>
        </div>
    </div>
</x-app-layout>
