{{-- resources/views/adv/offers/stats.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Статистика по офферам
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="bg-green-100 text-green-800 p-2 rounded mb-3">
                    {{ session('status') }}
                </div>
            @endif

            <div class="overflow-x-auto bg-white border rounded">
                <table class="w-full text-left">
                    <thead class="border-b bg-gray-50">
                        <tr class="text-gray-600">
                            <th class="p-3">Оффер</th>
                            <th class="p-3">Стоимость клика</th>
                            <th class="p-3">Активен</th>
                            <th class="p-3">Подписок</th>
                            <th class="p-3">Клики (всего)</th>
                            <th class="p-3">Клики (валидные)</th>
                            <th class="p-3">Детали</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($offers as $o)
                        <tr class="border-t hover:bg-gray-50">
                            <td class="p-3">
                                <div class="font-medium text-gray-900">{{ $o->name }}</div>
                                <div class="text-xs text-gray-500">ID: {{ $o->id }}</div>
                            </td>
                            <td class="p-3 whitespace-nowrap">
                                {{ number_format((float)$o->cpc, 4, '.', ' ') }} ₽
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-1 rounded text-xs {{ $o->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">
                                    {{ $o->is_active ? 'Да' : 'Нет' }}
                                </span>
                            </td>
                            <td class="p-3">{{ $o->subscriptions_count }}</td>
                            <td class="p-3">{{ $o->clicks_count }}</td>
                            <td class="p-3">{{ $o->valid_clicks_count }}</td>
                            <td class="p-3">
                                <a class="text-indigo-700 hover:underline"
                                   href="{{ route('adv.offers.subscriptions', $o) }}">
                                    Подписки
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="p-4 text-gray-600" colspan="7">
                                Нет офферов.
                                <a class="text-indigo-700 hover:underline" href="{{ route('adv.offers.index') }}">
                                    Перейти к созданию →
                                </a>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $offers->links() }}</div>
        </div>
    </div>
</x-app-layout>
