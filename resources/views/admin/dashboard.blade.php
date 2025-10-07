{{-- resources/views/dashboard.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Личный кабинет
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    Вы уже вошли.
                </div>
            </div>

            @auth
                @php $role = auth()->user()->role ?? null; @endphp

                <div class="grid md:grid-cols-3 gap-4 mt-6">
                    {{-- ====== Админ ====== --}}
                    @if($role === 'admin')
                        <a href="{{ route('admin.users') }}"
                           class="block p-4 bg-white border rounded hover:bg-gray-50 transition">
                            <div class="font-semibold mb-1">Пользователи</div>
                            <div class="text-sm text-gray-600">Список и управление</div>
                        </a>

                        <a href="{{ route('admin.offers') }}"
                           class="block p-4 bg-white border rounded hover:bg-gray-50 transition">
                            <div class="font-semibold mb-1">Офферы</div>
                            <div class="text-sm text-gray-600">Список и управление</div>
                        </a>

                        <a href="{{ route('admin.topics.index') }}"
                           class="block p-4 bg-white border rounded hover:bg-gray-50 transition">
                            <div class="font-semibold mb-1">Темы</div>
                            <div class="text-sm text-gray-600">Управление тематиками</div>
                        </a>

                        <a href="{{ route('admin.clicks') }}"
                           class="block p-4 bg-white border rounded hover:bg-gray-50 transition">
                            <div class="font-semibold mb-1">Клики</div>
                            <div class="text-sm text-gray-600">Журнал кликов и фильтры</div>
                        </a>
                    @endif

                    {{-- ====== Рекламодатель ====== --}}
                    @if($role === 'advertiser')
                        <a href="{{ route('adv.offers.index') }}"
                           class="block p-4 bg-white border rounded hover:bg-gray-50 transition">
                            <div class="font-semibold mb-1">Мои офферы</div>
                            <div class="text-sm text-gray-600">Список офферов рекламодателя</div>
                        </a>

                        <a href="{{ route('adv.stats') }}"
                           class="block p-4 bg-white border rounded hover:bg-gray-50 transition">
                            <div class="font-semibold mb-1">Статистика</div>
                            <div class="text-sm text-gray-600">Переходы и динамика</div>
                        </a>
                    @endif

                    {{-- ====== Веб-мастер ====== --}}
                    @if($role === 'webmaster')
                        <a href="{{ route('wm.offers') }}"
                           class="block p-4 bg-white border rounded hover:bg-gray-50 transition">
                            <div class="font-semibold mb-1">Офферы</div>
                            <div class="text-sm text-gray-600">Доступные офферы</div>
                        </a>

                        <a href="{{ route('wm.subs.index') }}"
                           class="block p-4 bg-white border rounded hover:bg-gray-50 transition">
                            <div class="font-semibold mb-1">Мои подписки</div>
                            <div class="text-sm text-gray-600">Подключённые офферы</div>
                        </a>
                    @endif
                </div>
            @endauth
        </div>
    </div>
</x-app-layout>
