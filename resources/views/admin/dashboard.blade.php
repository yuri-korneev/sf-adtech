{{-- resources/views/admin/dashboard.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Админ: дашборд
        </h2>
    </x-slot>

    <style>
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:.5rem; }
        .card-header { padding:.75rem 1rem; border-bottom:1px solid #e5e7eb; font-weight:600; }
        .card-body { padding:1rem; }
        .tabular-nums { font-variant-numeric: tabular-nums; }
    </style>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
        {{-- Сводные карточки --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="card"><div class="card-body">
                <div class="text-sm text-gray-500">Пользователи (всего)</div>
                <div class="text-2xl font-semibold tabular-nums">{{ $counts['users']['total'] ?? 0 }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="text-sm text-gray-500">Офферы (активные)</div>
                <div class="text-2xl font-semibold tabular-nums">{{ $counts['offers']['active'] ?? 0 }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="text-sm text-gray-500">Подписок (всего)</div>
                <div class="text-2xl font-semibold tabular-nums">{{ $counts['subs'] ?? 0 }}</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="text-sm text-gray-500">Клики (валид за период)</div>
                <div class="text-2xl font-semibold tabular-nums">{{ $counts['clicks']['valid'] ?? 0 }}</div>
            </div></div>
        </div>

        @if(isset($counts['clicks']['refused']))
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mt-3">
            <div class="card"><div class="card-body">
                <div class="text-sm text-gray-500">Отказы (не подписан)</div>
                <div class="text-2xl font-semibold tabular-nums">{{ $counts['clicks']['refused'] }}</div>
            </div></div>
        </div>
        @endif

        {{-- Последние клики --}}
        <div class="card mt-6">
            <div class="card-header">Последние клики</div>
            <div class="card-body overflow-x-auto">
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
                        @forelse($latestClicks as $c)
                            <tr class="border-t hover:bg-gray-50">
                                <td class="p-2 tabular-nums">{{ $c->id }}</td>
                                <td class="p-2">{{ $c->subscription?->offer?->name }}</td>
                                <td class="p-2">{{ $c->subscription?->webmaster?->name }}</td>
                                <td class="p-2 font-mono text-xs break-all">{{ $c->token }}</td>
                                <td class="p-2">{{ $c->is_valid ? 'Да' : 'Нет' }}</td>
                                <td class="p-2">{{ $c->invalid_reason ?? '' }}</td>
                                <td class="p-2 whitespace-nowrap tabular-nums">
                                    {{ optional($c->clicked_at ?? $c->created_at)->format('d.m.Y H:i:s') }}
                                </td>
                            </tr>
                        @empty
                            <tr><td class="p-2 text-gray-600" colspan="7">Нет данных.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
