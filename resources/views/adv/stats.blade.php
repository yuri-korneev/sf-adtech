{{-- resources/views/adv/stats.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Статистика рекламодателя
            </h2>

            <div class="flex items-center gap-2">
                <a href="{{ route('adv.offers.index') }}"
                   class="px-3 py-2 rounded-md shadow bg-white text-gray-900 border border-gray-300 hover:bg-gray-50">
                    Мои офферы
                </a>
                <a href="{{ route('adv.stats.csv', request()->query()) }}"
                   class="px-3 py-2 rounded-md shadow bg-white text-gray-900 border border-gray-300 hover:bg-gray-50">
                    Скачать CSV
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">

        {{-- Фильтры --}}
        <div class="bg-white border rounded p-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                {{-- Оффер --}}
                <div class="flex flex-col">
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

                {{-- Период --}}
                <div class="flex flex-col">
                    <label class="text-sm text-gray-600 mb-1">Период</label>
                    <select name="period" class="border rounded h-10 px-3 pr-12">
                        <option value="today"  {{ $period==='today'?'selected':'' }}>Сегодня</option>
                        <option value="7d"     {{ $period==='7d'?'selected':'' }}>7 дней</option>
                        <option value="30d"    {{ $period==='30d'?'selected':'' }}>30 дней</option>
                        <option value="custom" {{ $period==='custom'?'selected':'' }}>По дате</option>
                    </select>
                </div>

                {{-- С даты --}}
                <div class="flex flex-col">
                    <label class="text-sm text-gray-600 mb-1">С даты</label>
                    <input type="date" name="from"
                           value="{{ $from->toDateString() }}"
                           class="border rounded h-10 px-3">
                </div>

                {{-- По дату --}}
                <div class="flex flex-col">
                    <label class="text-sm text-gray-600 mb-1">По дату</label>
                    <input type="date" name="to"
                           value="{{ $to->toDateString() }}"
                           class="border rounded h-10 px-3">
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

        {{-- График --}}
        <div class="mt-6 bg-white border rounded p-4">
            <div class="font-semibold mb-3">График по дням</div>
            <canvas id="advChart" height="120"></canvas>
        </div>
        {{-- Таблица по дням --}}
        <div class="mt-6 bg-white border rounded overflow-x-auto" id="adv-stats-table">
            <style>
                /* Стиль локальный, действует только внутри #adv-stats-table */
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
                    <th class="p-3">Дата</th>
                    <th class="p-3 num">Клики (всего)</th>
                    <th class="p-3 num">Клики (валидные)</th>
                    <th class="p-3 num">Стоимость (₽)</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $r)
                    <tr class="border-t">
                        <td class="p-3">
                            {{ \Illuminate\Support\Carbon::parse($r['date'])->translatedFormat('d.m.Y') }}
                        </td>

                        <td class="p-3 num">{{ $r['clicks_total'] }}</td>
                        <td class="p-3 num">{{ $r['clicks_valid'] }}</td>
                        <td class="p-3 num">
                            {{ number_format((float)$r['cost'], 2, '.', ' ') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="p-4 text-center text-gray-600" colspan="4">
                            Нет данных за выбранный период.
                        </td>
                    </tr>
                @endforelse
                </tbody>
                <tfoot class="border-t font-semibold">
                <tr>
                    <td class="p-3">Итого</td>
                    <td class="p-3 num">{{ $totals['clicks_total'] }}</td>
                    <td class="p-3 num">{{ $totals['clicks_valid'] }}</td>
                    <td class="p-3 num">
                        {{ number_format((float)$totals['cost'], 2, '.', ' ') }}
                    </td>
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
        const labels      = rows.map(r => r.date);
        const clicksAll   = rows.map(r => r.clicks_total);
        const clicksValid = rows.map(r => r.clicks_valid);
        const cost        = rows.map(r => Number(r.cost));

        new Chart(el, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    { label: 'Клики (всего)',    data: clicksAll,   borderWidth: 2, tension: 0.3 },
                    { label: 'Клики (валидные)', data: clicksValid, borderWidth: 2, tension: 0.3 },
                    { label: 'Стоимость (₽)',    data: cost,        borderWidth: 2, tension: 0.3, yAxisID: 'y2' }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'top' } },
                scales: {
                    y:  { beginAtZero: true },
                    y2: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } }
                }
            }
        });
    })();
    </script>
</x-app-layout>
