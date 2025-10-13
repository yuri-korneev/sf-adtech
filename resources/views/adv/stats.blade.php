{{-- resources/views/adv/stats.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Статистика рекламодателя
            </h2>

            <div class="flex items-center gap-2">
                <a href="{{ route('adv.stats.csv', request()->query()) }}"
                   class="px-3 py-2 rounded-md shadow bg-white text-gray-900 border border-gray-300 hover:bg-gray-50">
                    Скачать CSV
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $commission = (float) config('sf.commission', 0.20);
        $commissionPct = (int) round($commission * 100);
        $wmPct = 100 - $commissionPct;
        $groupVal  = $group ?? request('group','day');   // day|month|year
        $periodVal = $period ?? request('period','30d'); // today|7d|30d|1y|custom
        $graphTitle = match($groupVal){ 'month'=>'График по месяцам', 'year'=>'График по годам', default=>'График по дням' };
    @endphp

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">

        {{-- Фильтры --}}
        <div class="bg-white border rounded p-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-8 gap-4 items-end">
                {{-- Оффер --}}
                <div class="flex flex-col md:col-span-2">
                    <label class="text-sm text-gray-600 mb-1">Оффер</label>
                    <select name="offer_id" class="border rounded h-10 px-3 pr-10">
                        <option value="">Все офферы</option>
                        @foreach($offers as $o)
                            <option value="{{ $o->id }}" {{ (string)$offerId === (string)$o->id ? 'selected':'' }}>
                                {{ $o->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Разрез (вынесли вперёд) --}}
                <div class="flex flex-col">
                    <label class="text-sm text-gray-600 mb-1">Разрез</label>
                    <select name="group" class="border rounded h-10 px-3 pr-12">
                        <option value="day"   {{ $groupVal==='day'?'selected':'' }}>День</option>
                        <option value="month" {{ $groupVal==='month'?'selected':'' }}>Месяц</option>
                        <option value="year"  {{ $groupVal==='year'?'selected':'' }}>Год</option>
                    </select>
                </div>

                {{-- Период --}}
                <div class="flex flex-col">
                    <label class="text-sm text-gray-600 mb-1">Период</label>
                    <select name="period" class="border rounded h-10 px-3 pr-12">
                        <option value="today"  {{ $periodVal==='today'?'selected':'' }}>Сегодня</option>
                        <option value="7d"     {{ $periodVal==='7d'?'selected':'' }}>7 дней</option>
                        <option value="30d"    {{ $periodVal==='30d'?'selected':'' }}>30 дней</option>
                        <option value="1y"     {{ $periodVal==='1y'?'selected':'' }}>1 год</option>
                        <option value="all"    {{ $periodVal==='all'?'selected':'' }}>Все время</option>
                        <option value="custom" {{ $periodVal==='custom'?'selected':'' }}>По дате</option>
                    </select>
                </div>

                {{-- С даты --}}
                <div class="flex flex-col">
                    <label class="text-sm text-gray-600 mb-1">С даты</label>
                    <input type="date" name="from" value="{{ $from->toDateString() }}" class="border rounded h-10 px-3">
                </div>

                {{-- По дату --}}
                <div class="flex flex-col">
                    <label class="text-sm text-gray-600 mb-1">По дату</label>
                    <input type="date" name="to" value="{{ $to->toDateString() }}" class="border rounded h-10 px-3">
                </div>

                {{-- Кнопки --}}
                <div class="self-end mt-2 md:mt-1 flex items-center gap-2">
                    <button class="h-10 w-32 px-4 rounded border bg-white hover:bg-gray-50">Применить</button>
                    @if(request()->query())
                        <a href="{{ route('adv.stats') }}"
                           class="h-10 px-4 rounded border bg-white hover:bg-gray-50 inline-flex items-center">
                            Сбросить
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Карточки итогов за период --}}
        @php
            $advCostTot = (float) ($totals['cost'] ?? 0);
            $wmPayoutTot = $advCostTot * (1 - $commission);
            $systemRevenueTot = $advCostTot * $commission;
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
            <div class="p-4 rounded border bg-white">
                <div class="text-sm text-gray-500">Расход рекламодателя</div>
                <div class="text-2xl font-semibold">
                    {{ number_format($advCostTot, 2, ',', ' ') }} ₽
                </div>
            </div>
            <div class="p-4 rounded border bg-white">
                <div class="text-sm text-gray-500">Выплата веб-мастерам ({{ 100 - (int)round($commission*100) }}%)</div>
                <div class="text-2xl font-semibold">
                    {{ number_format($wmPayoutTot, 2, ',', ' ') }} ₽
                </div>
            </div>
            <div class="p-4 rounded border bg-white">
                <div class="text-sm text-gray-500">Доход системы ({{ (int)round($commission*100) }}%)</div>
                <div class="text-2xl font-semibold">
                    {{ number_format($systemRevenueTot, 2, ',', ' ') }} ₽
                </div>
            </div>
        </div>

        {{-- График --}}
        <div class="mt-6 bg-white border rounded p-4">
            <div class="font-semibold mb-3">{{ $graphTitle }}</div>
            <canvas id="advChart" height="120"></canvas>
        </div>

        {{-- Таблица --}}
        <div class="mt-6 bg-white border rounded overflow-x-auto" id="adv-stats-table">
            <style>
                #adv-stats-table .num{
                    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                    font-variant-numeric: tabular-nums lining-nums;
                    font-feature-settings: "tnum" 1, "lnum" 1;
                    text-align: right;
                    white-space: nowrap;
                    letter-spacing: 0;
                }
            </style>

            <table class="w-full table-fixed text-left">
                <thead class="border-b bg-gray-50">
                    <tr class="text-gray-700 text-sm">
                        <th class="p-3">Период</th>
                        <th class="p-3 num">Клики (всего)</th>
                        <th class="p-3 num">Клики (валидные)</th>
                        <th class="p-3 num">Расход, ₽</th>
                        <th class="p-3 num">Выплата WM, ₽</th>
                        <th class="p-3 num">Доход системы, ₽</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $r)
                    @php
                        $advCost = (float)($r['cost'] ?? 0);
                        $wmPayout = $advCost * (1 - $commission);
                        $systemRevenue = $advCost * $commission;

                        // красивое форматирование периода в зависимости от разреза
                        $pretty = $r['date'];
                        try {
                            if ($groupVal === 'day')   $pretty = \Illuminate\Support\Carbon::parse($r['date'])->translatedFormat('d.m.Y');
                            if ($groupVal === 'month') $pretty = \Illuminate\Support\Carbon::parse($r['date'])->translatedFormat('m.Y');
                            if ($groupVal === 'year')  $pretty = \Illuminate\Support\Carbon::parse($r['date'])->translatedFormat('Y');
                        } catch (\Throwable $e) {}
                    @endphp
                    <tr class="border-t">
                        <td class="p-3">{{ $pretty }}</td>
                        <td class="p-3 num">{{ (int)$r['clicks_total'] }}</td>
                        <td class="p-3 num">{{ (int)$r['clicks_valid'] }}</td>
                        <td class="p-3 num">{{ number_format($advCost, 2, ',', ' ') }}</td>
                        <td class="p-3 num">{{ number_format($wmPayout, 2, ',', ' ') }}</td>
                        <td class="p-3 num">{{ number_format($systemRevenue, 2, ',', ' ') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="p-4 text-center text-gray-600" colspan="6">Нет данных за выбранный период.</td>
                    </tr>
                @endforelse
                </tbody>

                @php
                    $t_spend = (float) ($totals['cost'] ?? 0);
                    $t_wm    = $t_spend * (1 - $commission);
                    $t_sys   = $t_spend * $commission;
                @endphp
                <tfoot class="border-t font-semibold">
                <tr>
                    <td class="p-3">Итого</td>
                    <td class="p-3 num">{{ (int)($totals['clicks_total'] ?? 0) }}</td>
                    <td class="p-3 num">{{ (int)($totals['clicks_valid'] ?? 0) }}</td>
                    <td class="p-3 num">{{ number_format($t_spend, 2, ',', ' ') }}</td>
                    <td class="p-3 num">{{ number_format($t_wm,    2, ',', ' ') }}</td>
                    <td class="p-3 num">{{ number_format($t_sys,   2, ',', ' ') }}</td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Chart.js (CDN) + построение графика --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    (function () {
        const el = document.getElementById('advChart');
        if (!el || !window.Chart) return;

        const rows = @json($rows);
        const commission = {{ json_encode($commission) }};

        const labels      = rows.map(r => r.date);
        const clicksValid = rows.map(r => Number(r.clicks_valid || 0));
        const advCost     = rows.map(r => Number(r.cost || 0));

        new Chart(el, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    { label: 'Клики (валидные)', data: clicksValid, borderWidth: 2, tension: 0.3, yAxisID: 'y'  },
                    { label: 'Расход, ₽',        data: advCost,     borderWidth: 2, tension: 0.3, yAxisID: 'y1' },
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'top' } },
                scales: {
                    y:  { beginAtZero: true, title: { display: true, text: 'Клики' } },
                    y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: '₽' } }
                }
            }
        });
    })();
    </script>
</x-app-layout>
