{{-- resources/views/adv/dashboard.blade.php (home.blade.php) --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Рекламодатель: дашборд
        </h2>
    </x-slot>

    <style>
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:.5rem; }
        .card-header { padding:.75rem 1rem; border-bottom:1px solid #e5e7eb; font-weight:600; }
        .card-body { padding:1rem; }
        .tabular-nums { font-variant-numeric: tabular-nums; }
    </style>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">

        {{-- Сводные карточки (покажутся только если контроллер передал $counts) --}}
        @php $c = $counts ?? null; @endphp
        @if(is_array($c))
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="card"><div class="card-body">
                    <div class="text-sm text-gray-500">Офферы (всего)</div>
                    <div class="text-2xl font-semibold tabular-nums">{{ $c['offers']['total'] ?? 0 }}</div>
                </div></div>
                <div class="card"><div class="card-body">
                    <div class="text-sm text-gray-500">Офферы (активные)</div>
                    <div class="text-2xl font-semibold tabular-nums">{{ $c['offers']['active'] ?? 0 }}</div>
                </div></div>
                <div class="card"><div class="card-body">
                    <div class="text-sm text-gray-500">Подписок (всего)</div>
                    <div class="text-2xl font-semibold tabular-nums">{{ $c['subs']['total'] ?? 0 }}</div>
                </div></div>
                <div class="card"><div class="card-body">
                    <div class="text-sm text-gray-500">Клики (валид за период)</div>
                    <div class="text-2xl font-semibold tabular-nums">{{ $c['clicks']['valid'] ?? 0 }}</div>
                </div></div>
            </div>
        @endif

        {{-- Последние клики (покажется если есть $latestClicks) --}}
        @if(!empty($latestClicks))
            <div class="card mt-6">
                <div class="card-header">Последние клики</div>
                <div class="card-body overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="border-b bg-gray-50">
                        <tr class="text-gray-700 text-sm">
                            <th class="p-2">Время</th>
                            <th class="p-2">Оффер</th>
                            <th class="p-2">Токен</th>
                            <th class="p-2">Валиден</th>
                            <th class="p-2">Причина</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($latestClicks as $c)
                            <tr class="border-t hover:bg-gray-50">
                                <td class="p-2 tabular-nums">{{ optional($c->clicked_at ?? $c->created_at)->format('d.m.Y H:i:s') }}</td>
                                <td class="p-2">{{ $c->subscription?->offer?->name }}</td>
                                <td class="p-2 font-mono text-xs break-all">{{ $c->token }}</td>
                                <td class="p-2">{{ $c->is_valid ? 'Да' : 'Нет' }}</td>
                                <td class="p-2">{{ $c->invalid_reason ?? '' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Если контроллер ничего не передал — аккуратная заглушка --}}
        @if(empty($counts) && empty($latestClicks))
            <div class="bg-white border rounded p-6 mt-4 text-gray-700">
                Добро пожаловать! Здесь будет сводка по вашим офферам и кликам.
            </div>
        @endif
    </div>
</x-app-layout>
