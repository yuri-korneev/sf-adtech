{{-- resources/views/adv/dashboard.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Рекламодатель: дашборд</h2>
    </x-slot>

    <style>
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:.5rem; }
        .card-body { padding:1rem; }
        .tabular-nums { font-variant-numeric: tabular-nums; }
    </style>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
        {{-- сводка --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="card"><div class="card-body">
                <div class="text-sm text-gray-500">Клики (всего)</div>
                <div class="text-2xl font-semibold tabular-nums">{{ number_format($stats['clicks_all'] ?? 0, 0, '.', ' ') }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="text-sm text-gray-500">Клики (валидные)</div>
                <div class="text-2xl font-semibold tabular-nums">{{ number_format($stats['clicks_valid'] ?? 0, 0, '.', ' ') }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="text-sm text-gray-500">Расход (₽)</div>
                <div class="text-2xl font-semibold tabular-nums">{{ number_format($stats['cost_rub'] ?? 0, 2, '.', ' ') }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="text-sm text-gray-500">Подписок активных</div>
                <div class="text-2xl font-semibold tabular-nums">{{ number_format($stats['subs_active'] ?? 0, 0, '.', ' ') }}</div>
            </div></div>
        </div>

        {{-- последние клики --}}
        <div class="mt-6 overflow-x-auto bg-white border rounded">
            <table class="w-full text-left">
                <thead class="border-b bg-gray-50">
                <tr class="text-gray-700 text-sm">
                    <th class="p-2">ID</th>
                    <th class="p-2">Оффер</th>
                    <th class="p-2">Веб-мастер</th>
                    <th class="p-2">Токен</th>
                    <th class="p-2">Валиден</th>
                    <th class="p-2">Причина</th>
                    <th class="p-2">Время</th>
                </tr>
                </thead>
                <tbody>
                @forelse($lastClicks as $c)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="p-2 tabular-nums">{{ $c->id }}</td>
                        <td class="p-2">{{ $c->subscription?->offer?->name }}</td>
                        <td class="p-2">{{ $c->subscription?->webmaster?->name }}</td>
                        <td class="p-2 font-mono text-xs break-all">{{ $c->token }}</td>
                        <td class="p-2">{{ $c->is_valid ? 'Да' : 'Нет' }}</td>
                        <td class="p-2">{{ $c->invalid_reason }}</td>
                        <td class="p-2 whitespace-nowrap tabular-nums">
                            {{ optional($c->clicked_at)->format('d.m.Y H:i:s') }}
                        </td>
                    </tr>
                @empty
                    <tr><td class="p-4 text-gray-600" colspan="7">Нет кликов.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $lastClicks->links() }}</div>
    </div>
</x-app-layout>
