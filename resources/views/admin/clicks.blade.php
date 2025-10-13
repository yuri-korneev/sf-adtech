{{-- resources/views/admin/clicks.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Админ: клики</h2>

            <div class="flex gap-2">
                {{-- УДАЛЕНО: кнопка «Выданные ссылки» --}}
                <a href="{{ route('admin.revenue.csv', request()->query()) }}"
                   class="px-3 py-2 rounded-md shadow bg-white text-gray-900 border border-gray-300 hover:bg-gray-50">
                    Доходы (CSV)
                </a>
                <a href="{{ route('admin.clicks.csv', request()->query()) }}"
                   class="px-3 py-2 rounded-md shadow bg-white text-gray-900 border border-gray-300 hover:bg-gray-50">
                    Экспорт CSV
                </a>
            </div>
        </div>
    </x-slot>

    <style>
        .ua-cell { max-width: 22rem; }
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:.5rem; }
        .card-header { padding:.75rem 1rem; border-bottom:1px solid #e5e7eb; font-weight:600; }
        .card-body { padding:1rem; }
        .tabular-nums { font-variant-numeric: tabular-nums; }
    </style>

    @php
        $periodVal = $period ?? (request('period') ?? '30d');
        $validVal  = request('valid');           // для обратной совместимости
        $typeVal   = request('type','all');      // all|valid|refused — приоритетный фильтр
        $qVal      = request('q');               // поиск по токену
    @endphp

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">

        {{-- Фильтры --}}
        <div class="bg-white border rounded p-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                <div class="flex flex-col">
                    <label class="text-sm text-gray-600 mb-1">Тип</label>
                    <select name="type" class="border rounded h-10 px-3 pr-10">
                        <option value="all"     {{ $typeVal==='all'?'selected':'' }}>Все</option>
                        <option value="valid"   {{ $typeVal==='valid'?'selected':'' }}>Валидные</option>
                        <option value="refused" {{ $typeVal==='refused'?'selected':'' }}>Отказы (не подписан)</option>
                    </select>
                </div>

                <div class="flex flex-col">
                    <label class="text-sm text-gray-600 mb-1">Статус</label>
                    <select name="valid" class="border rounded h-10 px-3 pr-10">
                        <option value="">Все</option>
                        <option value="1" {{ $validVal==='1'?'selected':'' }}>Валидные</option>
                        <option value="0" {{ $validVal==='0'?'selected':'' }}>Невалидные</option>
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
                    <input type="date" name="from" value="{{ optional($from)->format('Y-m-d') }}"
                           class="border rounded h-10 px-3">
                </div>

                <div class="flex flex-col">
                    <label class="text-sm text-gray-600 mb-1">По дату</label>
                    <input type="date" name="to" value="{{ optional($to)->format('Y-m-d') }}"
                           class="border rounded h-10 px-3">
                </div>

                <div class="flex flex-col">
                    <label class="text-sm text-gray-600 mb-1">Токен</label>
                    <input type="text" name="q" value="{{ $qVal }}" placeholder="часть токена"
                           class="border rounded h-10 px-3">
                </div>

                <div class="self-end mt-2 md:mt-1 flex items-center gap-2 md:col-span-6">
                    <button class="h-10 px-4 rounded border bg-white hover:bg-gray-50">
                        Применить
                    </button>
                    @if(request()->query())
                        <a href="{{ route('admin.clicks') }}"
                           class="h-10 px-4 rounded border bg-white hover:bg-gray-50 inline-flex items-center">
                            Сбросить
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Сводка по периоду --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-6">
            <div class="card"><div class="card-body">
                <div class="text-sm text-gray-500">Всего кликов</div>
                <div id="s-all" class="text-2xl font-semibold tabular-nums">—</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="text-sm text-gray-500">Валидные</div>
                <div id="s-valid" class="text-2xl font-semibold tabular-nums">—</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="text-sm text-gray-500">Невалидные</div>
                <div id="s-invalid" class="text-2xl font-semibold tabular-nums">—</div>
            </div></div>
            <div class="card"><div class="card-body">
                <div class="text-sm text-gray-500">Отказы (не подписан)</div>
                <div id="s-refused" class="text-2xl font-semibold tabular-nums">—</div>
            </div></div>
        </div>

        {{-- Графики --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">
            <div class="card lg:col-span-2">
                <div class="card-header">Клики по дням</div>
                <div class="card-body"><div style="height:300px"><canvas id="chartClicksByDay"></canvas></div></div>
            </div>
            <div class="card">
                <div class="card-header">Доля валидных/невалидных/отказов</div>
                <div class="card-body"><div style="height:300px"><canvas id="chartShare"></canvas></div></div>
            </div>
        </div>

        {{-- Таблица --}}
        <div class="mt-6 overflow-x-auto bg-white border rounded">
            <table class="w-full text-left">
                <thead class="border-b bg-gray-50">
                <tr class="text-gray-700 text-sm">
                    <th class="p-2">ID</th>
                    <th class="p-2">Оффер</th>
                    <th class="p-2">Веб-мастер</th>
                    <th class="p-2">Токен</th>
                    <th class="p-2">Валиден</th>
                    <th class="p-2">Причина (если не валид)</th>
                    <th class="p-2">Время</th>
                    <th class="p-2">IP</th>
                    <th class="p-2">Браузер</th>
                </tr>
                </thead>
                <tbody>
                @forelse($clicks as $c)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="p-2 tabular-nums">{{ $c->id }}</td>
                        <td class="p-2">{{ $c->subscription?->offer?->name }}</td>
                        <td class="p-2">{{ $c->subscription?->webmaster?->name }}</td>
                        <td class="p-2 font-mono text-xs break-all">{{ $c->token }}</td>
                        <td class="p-2">{{ $c->is_valid ? 'Да' : 'Нет' }}</td>
                        <td class="p-2">{{ $c->invalid_reason }}</td>
                        <td class="p-2 whitespace-nowrap tabular-nums">
                            {{ optional($c->clicked_at ?? $c->created_at)->format('d.m.Y H:i:s') }}
                        </td>
                        <td class="p-2">{{ $c->ip }}</td>
                        <td class="p-2 ua-cell truncate" title="{{ $c->user_agent }}">
                            {{ \Illuminate\Support\Str::limit($c->user_agent, 70) }}
                        </td>
                    </tr>
                @empty
                    <tr><td class="p-4 text-gray-600" colspan="9">Нет данных.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $clicks->links() }}</div>
    </div>

    {{-- Chart.js + загрузка агрегатов с /admin/clicks/stats --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    (function () {
      if (!window.Chart) return;

      const qs  = window.location.search || '';
      const url = "{{ route('admin.clicks.stats') }}" + qs;

      const baseLineOptions = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'top', labels: { boxWidth: 12 } },
          tooltip: {
            callbacks: {
              label: (ctx) => {
                const v = ctx.parsed.y ?? 0;
                return `${ctx.dataset.label}: ${Number.isFinite(v) ? v.toLocaleString('ru-RU') : v}`;
              }
            }
          }
        },
        scales: {
          x: { grid: { display: true, color: 'rgba(0,0,0,0.08)' }, ticks: { color: '#6b7280' } },
          y: {
            beginAtZero: true,
            suggestedMin: 0,
            suggestedMax: 10,
            ticks: { stepSize: 1, color: '#6b7280', precision: 0 },
            grid: { display: true, color: 'rgba(0,0,0,0.08)' }
          }
        }
      };

      fetch(url, { headers: { 'Cache-Control': 'no-store' } })
        .then(r => r.json())
        .then(data => {
          const labels  = data.labels || [];
          const valid   = data.series?.valid   || [];
          const invalid = data.series?.invalid || [];
          const refused = data.series?.refused || [];

          const maxY = Math.max(1, ...valid, ...invalid, ...refused);
          const yMax = maxY <= 10 ? 10 : Math.ceil(maxY * 1.1);
          baseLineOptions.scales.y.suggestedMax = yMax;
          baseLineOptions.scales.y.ticks.stepSize = Math.max(1, Math.round(yMax / 10));

          document.getElementById('s-all').textContent     = (data.totals?.all     ?? 0).toLocaleString('ru-RU');
          document.getElementById('s-valid').textContent   = (data.totals?.valid   ?? 0).toLocaleString('ru-RU');
          document.getElementById('s-invalid').textContent = (data.totals?.invalid ?? 0).toLocaleString('ru-RU');
          document.getElementById('s-refused').textContent = (data.totals?.refused ?? 0).toLocaleString('ru-RU');

          const ctxLine = document.getElementById('chartClicksByDay');
          if (ctxLine) {
            new Chart(ctxLine, {
              type: 'line',
              data: {
                labels,
                datasets: [
                  { label: 'Валидные',   data: valid,   borderWidth: 2, tension: 0.3, pointRadius: 2 },
                  { label: 'Невалидные', data: invalid, borderWidth: 2, tension: 0.3, pointRadius: 2 },
                  { label: 'Отказы',     data: refused, borderWidth: 2, tension: 0.3, pointRadius: 2 }
                ]
              },
              options: baseLineOptions
            });
          }

          const ctxDonut = document.getElementById('chartShare');
          if (ctxDonut) {
            new Chart(ctxDonut, {
              type: 'doughnut',
              data: {
                labels: ['Валидные','Невалидные','Отказы'],
                datasets: [{ data: [data.totals?.valid||0, data.totals?.invalid||0, data.totals?.refused||0] }]
              },
              options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '65%' }
            });
          }
        })
        .catch(console.error);
    })();
    </script>
</x-app-layout>
