{{-- resources/views/wm/stats.blade.php --}}
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
    @endphp

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
        {{-- Фильтры --}}
        <div class="bg-white border rounded p-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
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
        </div>

        {{-- График --}}
        <div class="mt-6 bg-white border rounded p-4">
            <div class="font-semibold mb-3">График по дням</div>
            <canvas id="wmChart" height="120"></canvas>
        </div>

        {{-- Таблица --}}
        <div class="mt-6 bg-white border rounded overflow-x-auto">
            <table class="w-full table-fixed text-left">
                <thead class="border-b bg-gray-50">
                <tr class="text-gray-700 text-sm">
                    <th class="p-3">Дата</th>
                    <th class="p-3 text-right tabular-nums">Клики (всего)</th>
                    <th class="p-3 text-right tabular-nums">Клики (валидные)</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $r)
                    <tr class="border-t">
                        <td class="p-3">
                            {{ \Illuminate\Support\Carbon::parse($r['date'])->translatedFormat('d.m.Y') }}
                        </td>
                        <td class="p-3 text-right tabular-nums">{{ $r['clicks_total'] }}</td>
                        <td class="p-3 text-right tabular-nums">{{ $r['clicks_valid'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="p-4 text-center text-gray-600" colspan="3">Нет данных за выбранный период.</td>
                    </tr>
                @endforelse
                </tbody>
                <tfoot class="border-t font-semibold">
                <tr>
                    <td class="p-3">Итого</td>
                    <td class="p-3 text-right tabular-nums">{{ $totals['clicks_total'] }}</td>
                    <td class="p-3 text-right tabular-nums">{{ $totals['clicks_valid'] }}</td>
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

            new Chart(el, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        { label: 'Клики (всего)',    data: total, borderWidth: 2, tension: 0.3, pointRadius: 2 },
                        { label: 'Клики (валидные)', data: valid, borderWidth: 2, tension: 0.3, pointRadius: 2 },
                    ]
                },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        x: { grid: { display: true, color: 'rgba(0,0,0,0.08)' }, ticks: { color: '#6b7280' } },
                        y: { beginAtZero: true, grid: { display: true, color: 'rgba(0,0,0,0.08)' }, ticks: { color: '#6b7280' } }
                    }
                }
            });
          })
          .catch(console.error);
    })();
    </script>
</x-app-layout>
