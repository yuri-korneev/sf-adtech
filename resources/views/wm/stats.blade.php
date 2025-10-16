{{-- resources/views/wm/stats.blade.php --}}

<noscript>
  <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 p-3 rounded mb-4">
    Для корректной работы этой страницы (графики, интерактив, перетаскивание) требуется включить JavaScript.
  </div>
</noscript>


<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Статистика веб-мастера</h2>
            <a href="{{ route('wm.stats.csv', request()->query()) }}"
               class="px-3 py-2 rounded-md shadow bg-white text-gray-900 border border-gray-300 hover:bg-gray-50">
                Скачать CSV
            </a>
        </div>
    </x-slot>

    <style>.tabular-nums{font-variant-numeric:tabular-nums}</style>
    @php
        $periodVal = $period ?? (request('period') ?? '30d');
        $offerId   = $offerId ?? request('offer_id');
        $groupVal  = $group   ?? request('group','day'); // day|month|year
        $commission = $commission ?? (float) config('sf.commission', 0.20);
        $graphTitle = match($groupVal){ 'month' => 'График по месяцам', 'year' => 'График по годам', default => 'График по дням' };
    @endphp

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
        {{-- Фильтры --}}
        <div class="bg-white border rounded p-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-7 gap-4 items-end">
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


                {{-- Разрез --}}
                <div class="flex flex-col">
                    <label class="text-sm text-gray-600 mb-1">Разрез</label>
                    <select name="group" class="border rounded h-10 px-3 pr-12">
                        <option value="day"   {{ $groupVal==='day'?'selected':'' }}>День</option>
                        <option value="month" {{ $groupVal==='month'?'selected':'' }}>Месяц</option>
                        <option value="year"  {{ $groupVal==='year'?'selected':'' }}>Год</option>
                    </select>
                </div>


                <div class="flex flex-col">
                    <label class="text-sm text-gray-600 mb-1">Период</label>
                    <select name="period" class="border rounded h-10 px-3 pr-12">
                        <option value="today"  {{ $periodVal==='today'?'selected':'' }}>Сегодня</option>
                        <option value="7d"     {{ $periodVal==='7d'?'selected':'' }}>7 дней</option>
                        <option value="30d"    {{ $periodVal==='30d'?'selected':'' }}>30 дней</option>
                        <option value="custom" {{ $periodVal==='custom'?'selected':'' }}>По дате</option>
                    </select>
                </div>

                <div class="flex flex-col">
                    <label class="text-sm text-gray-600 mb-1">С даты</label>
                    <input type="date" name="from" value="{{ optional($from)->toDateString() }}"
                           class="border rounded h-10 px-3">
                </div>
                <div class="flex flex-col">
                    <label class="text-sm text-gray-600 mb-1">По дату</label>
                    <input type="date" name="to" value="{{ optional($to)->toDateString() }}"
                           class="border rounded h-10 px-3">
                </div>

                <div class="self-end mt-2 md:mt-1 flex items-center gap-2">
                    <button class="h-10 px-4 rounded border bg-white hover:bg-gray-50">Применить</button>
                    @if(request()->query())
                        <a href="{{ route('wm.stats') }}"
                           class="h-10 px-4 rounded border bg-white hover:bg-gray-50 inline-flex items-center">
                            Сбросить
                        </a>
                    @endif
                </div>
            </form>
            <div class="text-xs text-gray-500 mt-2">
                Текущая комиссия системы: {{ number_format($commission*100, 0) }}%
            </div>
        </div>

        {{-- График --}}
        <div class="mt-6 bg-white border rounded p-4">
            <div class="font-semibold mb-3">{{ $graphTitle }}</div>
            <canvas id="wmChart" height="120"></canvas>
        </div>

        {{-- Таблица --}}
        <div class="mt-6 bg-white border rounded overflow-x-auto">
            <table class="w-full table-fixed text-left">
                <thead class="border-b bg-gray-50">
                <tr class="text-gray-700 text-sm">
                    <th class="p-3">Период</th>
                    <th class="p-3 text-right tabular-nums">Клики (всего)</th>
                    <th class="p-3 text-right tabular-nums">Клики (валидные)</th>
                    <th class="p-3 text-right tabular-nums">Доход WM</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $r)
                    <tr class="border-t">
                        <td class="p-3">
                            @php
                                $dRaw = $r['date'] ?? '';
                                $pretty = $dRaw;
                                try {
                                    if ($groupVal === 'day') {
                                        $pretty = \Illuminate\Support\Carbon::parse($dRaw)->translatedFormat('d.m.Y');
                                    } elseif ($groupVal === 'month') {
                                        $pretty = \Illuminate\Support\Carbon::parse($dRaw)->translatedFormat('m.Y');
                                    } elseif ($groupVal === 'year') {
                                        $pretty = \Illuminate\Support\Carbon::parse($dRaw)->translatedFormat('Y');
                                    }
                                } catch (\Throwable $e) {}
                            @endphp
                            {{ $pretty }}
                        </td>
                        <td class="p-3 text-right tabular-nums">{{ $r['clicks_total'] }}</td>
                        <td class="p-3 text-right tabular-nums">{{ $r['clicks_valid'] }}</td>
                        <td class="p-3 text-right tabular-nums">
                            {{ number_format((float)($r['wm_revenue'] ?? 0), 2, ',', ' ') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="p-4 text-center text-gray-600" colspan="4">Нет данных за выбранный период.</td>
                    </tr>
                @endforelse
                </tbody>
                <tfoot class="border-t font-semibold">
                <tr>
                    <td class="p-3">Итого</td>
                    <td class="p-3 text-right tabular-nums">{{ $totals['clicks_total'] ?? 0 }}</td>
                    <td class="p-3 text-right tabular-nums">{{ $totals['clicks_valid'] ?? 0 }}</td>
                    <td class="p-3 text-right tabular-nums">
                        {{ number_format((float)($totals['wm_revenue'] ?? 0), 2, ',', ' ') }}
                    </td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    (function () {
        const el = document.getElementById('wmChart');
        if (!el || !window.Chart) return;

        const url = "{{ route('wm.stats.data') }}" + (window.location.search || '');
        fetch(url, { headers: { 'Cache-Control': 'no-store' } })
          .then(r => r.json())
          .then(data => {
            const labels = data.labels || [];
            const total  = data.series?.total || [];
            const valid  = data.series?.valid || [];
            const revenue= data.series?.revenue || [];

            new Chart(el, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        { label: 'Клики (всего)',    data: total,  borderWidth: 2, tension: 0.3, pointRadius: 2, yAxisID: 'y'  },
                        { label: 'Клики (валидные)', data: valid,  borderWidth: 2, tension: 0.3, pointRadius: 2, yAxisID: 'y'  },
                        { label: 'Доход WM',         data: revenue, borderWidth: 2, tension: 0.3, pointRadius: 2, yAxisID: 'y1' },
                    ]
                },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        x: { grid: { display: true, color: 'rgba(0,0,0,0.08)' }, ticks: { color: '#6b7280' } },
                        y: { beginAtZero: true, grid: { display: true, color: 'rgba(0,0,0,0.08)' }, ticks: { color: '#6b7280' } },
                        y1:{ beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { color: '#6b7280' } }
                    }
                }
            });
          })
          .catch(console.error);
    })();
    </script>
</x-app-layout>
