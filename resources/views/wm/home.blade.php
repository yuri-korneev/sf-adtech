{{-- resources/views/wm/dashboard.blade.php (home.blade.php) --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Веб-мастер: дашборд
        </h2>
    </x-slot>

    <style>
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:.5rem; }
        .card-header { padding:.75rem 1rem; border-bottom:1px solid #e5e7eb; font-weight:600; }
        .card-body { padding:1rem; }
        .tabular-nums { font-variant-numeric: tabular-nums; }
    </style>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">

        {{-- Сводка, если контроллер дал $counts --}}
        @php $c = $counts ?? null; @endphp
        @if(is_array($c))
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="card"><div class="card-body">
                    <div class="text-sm text-gray-500">Доступные офферы</div>
                    <div class="text-2xl font-semibold tabular-nums">{{ $c['offers']['available'] ?? 0 }}</div>
                </div></div>
                <div class="card"><div class="card-body">
                    <div class="text-sm text-gray-500">Мои подписки (активные)</div>
                    <div class="text-2xl font-semibold tabular-nums">{{ $c['subs']['active'] ?? 0 }}</div>
                </div></div>
                <div class="card"><div class="card-body">
                    <div class="text-sm text-gray-500">Клики (валид за период)</div>
                    <div class="text-2xl font-semibold tabular-nums">{{ $c['clicks']['valid'] ?? 0 }}</div>
                </div></div>
                <div class="card"><div class="card-body">
                    <div class="text-sm text-gray-500">Клики (все за период)</div>
                    <div class="text-2xl font-semibold tabular-nums">{{ $c['clicks']['all'] ?? 0 }}</div>
                </div></div>
            </div>
        @endif

        {{-- Недавние клики, если есть $latestClicks --}}
        @if(!empty($latestClicks))
            <div class="card mt-6">
                <div class="card-header">Недавние клики</div>
                <div class="card-body overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="border-b bg-gray-50">
                            <tr class="text-gray-700 text-sm">
                                <th class="p-2">Время</th>
                                <th class="p-2">Оффер</th>
                                <th class="p-2">Токен</th>
                                <th class="p-2">Валиден</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($latestClicks as $c)
                                <tr class="border-t hover:bg-gray-50">
                                    <td class="p-2 tabular-nums">{{ optional($c->clicked_at ?? $c->created_at)->format('d.m.Y H:i:s') }}</td>
                                    <td class="p-2">{{ $c->subscription?->offer?->name }}</td>
                                    <td class="p-2 font-mono text-xs break-all">{{ $c->token }}</td>
                                    <td class="p-2">{{ $c->is_valid ? 'Да' : 'Нет' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Заглушка, если контроллер ничего не передал --}}
        @if(empty($counts) && empty($latestClicks))
            <div class="bg-white border rounded p-6 mt-4 text-gray-700">
                Добро пожаловать! Здесь появится сводка по вашим подпискам и кликам.
            </div>
        @endif
    </div>
</x-app-layout>
