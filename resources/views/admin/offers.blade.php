<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Офферы</h2>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="bg-green-100 text-green-800 p-2 rounded mb-3">{{ session('status') }}</div>
        @endif

        <form method="GET" class="mb-3 flex items-center gap-3">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Поиск: название/URL"
                   class="border p-2 rounded w-64">
            <select name="active" class="border rounded h-10 px-3 pr-16 bg-white w-48 md:w-56 focus:outline-none focus:ring-2 focus:ring-gray-300">

                <option value="">Любой статус</option>
                <option value="1" {{ request('active')==='1'?'selected':'' }}>Активные</option>
                <option value="0" {{ request('active')==='0'?'selected':'' }}>Выключенные</option>
            </select>
            <button class="px-3 py-2 rounded border bg-white hover:bg-gray-50">Фильтровать</button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left border">
                <thead>
                    <tr>
                        <th class="p-2">ID</th>
                        <th class="p-2">Название</th>
                        <th class="p-2">Рекламодатель</th>
                        <th class="p-2">CPC</th>
                        <th class="p-2">Активен</th>
                        <th class="p-2">Подписок</th>
                        <th class="p-2">Клики (всего)</th>
                        <th class="p-2">Клики (валидные)</th>
                        <th class="p-2">Действия</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($offers as $o)
                    <tr class="border-t">
                        <td class="p-2">{{ $o->id }}</td>
                        <td class="p-2">{{ $o->name }}</td>
                        <td class="p-2">
                            {{ $o->advertiser?->name }}
                            <div class="text-xs text-gray-500">{{ $o->advertiser?->email }}</div>
                        </td>
                        <td class="p-2">{{ $o->cpc }}</td>
                        <td class="p-2">{{ $o->is_active ? 'Да' : 'Нет' }}</td>
                        <td class="p-2">{{ $o->subscriptions_count }}</td>
                        <td class="p-2">{{ $o->clicks_count }}</td>
                        <td class="p-2">{{ $o->valid_clicks_count }}</td>
                        <td class="p-2">
                            <form class="inline" method="POST" action="{{ route('admin.offers.toggle', $o) }}">
                                @csrf
                                <button class="px-2 py-1 rounded border">
                                    {{ $o->is_active ? 'Выключить' : 'Включить' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td class="p-2" colspan="9">Нет офферов</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $offers->links() }}</div>
    </div>
</x-app-layout>
