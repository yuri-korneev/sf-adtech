<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Личный кабинет
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-x-3">
            <a
                href="{{ route('wm.offers') }}"
                class="inline-flex items-center px-3 py-2 rounded-md shadow
                       bg-white text-gray-900 border border-gray-300
                       hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300"
            >
                Офферы
            </a>

            <a
                href="{{ route('wm.subs.index') }}"
                class="inline-flex items-center px-3 py-2 rounded-md shadow
                       bg-white text-gray-900 border border-gray-300
                       hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300"
            >
                Мои подписки
            </a>
            <a href="{{ route('wm.stats') }}"
                class="px-3 py-2 rounded-md shadow bg-white text-gray-900 border border-gray-300 hover:bg-gray-50">
                Статистика
            </a>

        </div>
    </div>
</x-app-layout>
