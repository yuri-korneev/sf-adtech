{{-- resources/views/adv/offers/index.blade.php --}}

<noscript>
  <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 p-3 rounded mb-4">
    Для корректной работы этой страницы (графики, интерактив, перетаскивание) требуется включить JavaScript.
  </div>
</noscript>


<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Мои офферы
            </h2>

            <a
                href="{{ route('adv.offers.create') }}"
                class="px-3 py-2 rounded-md shadow
                       bg-white text-gray-900 border border-gray-300
                       hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300"
            >
                Создать оффер
            </a>
        </div>
    </x-slot>

    {{-- Сообщение для пользователей без JS (требование ТЗ) --}}
    <noscript>
        <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 p-3 rounded mb-4 mx-4">
            Для корректной работы Kanban и некоторых фильтров требуется включить JavaScript.
        </div>
    </noscript>

    {{-- Локальные стили: таблица + Kanban --}}
    <style>
        /* ====== Таблица ====== */
        #offersTable thead th { text-align: center !important; vertical-align: middle !important; }
        #offersTable tbody td { text-align: center !important; vertical-align: middle !important; }

        /* ширины 7 колонок */
        #offersTable th:nth-child(1) { width: 22% !important; } /* Название */
        #offersTable th:nth-child(2) { width: 12% !important; } /* CPC */
        #offersTable th:nth-child(3) { width: 30% !important; } /* URL */
        #offersTable th:nth-child(4) { width: 10% !important; } /* Статус */
        #offersTable th:nth-child(5) { width: 12% !important; } /* Темы */
        #offersTable th:nth-child(6) { width: 8%  !important; } /* Подписано */
        #offersTable th:nth-child(7) { width: 6%  !important; } /* Действия */

        /* перенос длинных URL */
        #offersTable td.url a {
            word-break: break-word;
            overflow-wrap: anywhere;
            display: inline-block;
            max-width: 100%;
        }

        /* действия */
        #offersTable .action-link {
            color: #1d4ed8; /* blue-700 */
            text-decoration: underline;
            transition: color .15s ease;
        }
        #offersTable .action-link:hover { color: #1e3a8a !important; }
        #offersTable .action-btn {
            color: #b91c1c; background: transparent; border: none;
            text-decoration: underline; padding: 0; cursor: pointer;
            transition: color .15s ease;
        }
        #offersTable .action-btn:hover { color: #7f1d1d !important; }

        /* ====== Kanban ====== */
        #offers-kanban { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 768px) { #offers-kanban { grid-template-columns: repeat(4, minmax(0, 1fr)); } }

        .kanban-col {
            min-height: 96px;
            display: flex; flex-direction: column; gap: .5rem;
            background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; padding: .75rem;
        }
        .kanban-col-title { font-weight: 600; margin-bottom: .25rem; }

        .kanban-card {
            background: #f9fafb; border: 1px solid #d1d5db; border-radius: .5rem;
            padding: .5rem .625rem; cursor: grab;
        }
        .kanban-card:active { cursor: grabbing; }
        .kanban-card .name { font-size: .875rem; font-weight: 600; color: #111827; }
        .kanban-card .url  { font-size: .75rem;  color: #6b7280; word-break: break-word; }
    </style>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Фильтры --}}
            <form method="GET" class="mb-4 flex flex-wrap items-center gap-3">
                <input
                    type="text"
                    name="q"
                    value="{{ $q ?? request('q') }}"
                    placeholder="Название или URL…"
                    class="border rounded h-10 px-3 w-64"
                >

                @php $statusVal = $status ?? request('status'); @endphp
                <select name="status" class="border rounded h-10 px-3 pr-8">
                    <option value="" {{ $statusVal===null || $statusVal==='' ? 'selected':'' }}>Любой статус</option>
                    <option value="active"   {{ $statusVal==='active'   ? 'selected':'' }}>Только активные</option>
                    <option value="inactive" {{ $statusVal==='inactive' ? 'selected':'' }}>Только неактивные</option>
                </select>

                <button class="px-4 py-2 rounded border bg-white hover:bg-gray-50">
                    Применить
                </button>

                @if(($q ?? '') !== '' || ($statusVal ?? '') !== '')
                    <a href="{{ route('adv.offers.index') }}" class="px-4 py-2 rounded border bg-white hover:bg-gray-50">
                        Сбросить
                    </a>
                @endif
            </form>

            @if (session('status'))
                <div class="bg-green-100 text-green-800 p-2 rounded mb-3">
                    {{ session('status') }}
                </div>
            @endif

            {{-- ===== Kanban: статусы офферов ===== --}}
            <div id="offers-kanban" class="my-6">
                @php
                    $cols = [
                        'draft'    => 'Черновик',
                        'active'   => 'Активен',
                        'paused'   => 'Пауза',
                        'archived' => 'Архив',
                    ];
                @endphp

                @foreach($cols as $code => $title)
                    <div class="kanban-col" data-status="{{ $code }}">
                        <div class="kanban-col-title">{{ $title }}</div>

                        @foreach($offers->where('status', $code) as $o)
                            <div class="kanban-card" data-offer-id="{{ $o->id }}">
                                <div class="name">{{ $o->name }}</div>
                                <div class="url">{{ $o->target_url }}</div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            {{-- Таблица --}}
            <div class="overflow-x-auto bg-white border rounded">
                <table id="offersTable" class="w-full">
                    <thead class="border-b bg-gray-50">
                        <tr class="text-gray-700 text-sm">
                            <th class="p-3">Название</th>
                            <th class="p-3">Стоимость клика (₽)</th>
                            <th class="p-3">Целевой URL</th>
                            <th class="p-3">Статус</th>
                            <th class="p-3">Темы</th>
                            <th class="p-3">Подписано (активные)</th>
                            <th class="p-3">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($list as $o)
                            <tr class="border-t hover:bg-gray-50">
                                {{-- Название --}}
                                <td class="p-3">
                                    <div class="font-medium text-gray-900">{{ $o->name }}</div>
                                    <div class="text-xs text-gray-500">ID: {{ $o->id }}</div>
                                </td>

                                {{-- CPC (источник истины по ТЗ) --}}
                                <td class="p-3 whitespace-nowrap">
                                    {{ number_format((float)$o->cpc, 4, ',', ' ') }}
                                </td>

                                {{-- URL --}}
                                <td class="p-3 url">
                                    <a class="text-indigo-700 underline hover:text-indigo-900"
                                       href="{{ $o->target_url }}" target="_blank" rel="noopener">
                                        {{ $o->target_url }}
                                    </a>
                                </td>

                                {{-- Статус --}}
                                <td class="p-3">
                                    @php
                                        // если новое поле 'status' есть — используем его, иначе резервно по is_active
                                        $statusCode = $o->status ?? ($o->is_active ? 'active' : 'paused');
                                    @endphp
                                    @if($statusCode === 'active')
                                        <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">Активен</span>
                                    @elseif($statusCode === 'paused')
                                        <span class="px-2 py-1 rounded text-xs bg-gray-200 text-gray-700">Пауза</span>
                                    @elseif($statusCode === 'draft')
                                        <span class="px-2 py-1 rounded text-xs bg-amber-100 text-amber-800">Черновик</span>
                                    @else
                                        <span class="px-2 py-1 rounded text-xs bg-slate-200 text-slate-700">Архив</span>
                                    @endif
                                </td>

                                {{-- Темы --}}
                                <td class="p-3">
                                    @forelse($o->topics as $t)
                                        <span class="inline-block text-xs px-2 py-1 bg-gray-100 text-gray-700 rounded mr-1 mb-1">
                                            {{ $t->name }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-gray-500">нет</span>
                                    @endforelse
                                </td>

                                {{-- Подписки --}}
                                <td class="p-3 text-right">{{ (int) $o->subscriptions_count }}</td>

                                {{-- Действия --}}
                                <td class="p-3 whitespace-nowrap">
                                    <a class="action-link" href="{{ route('adv.offers.edit', $o) }}">
                                        Редактировать
                                    </a>

                                    <form class="inline"
                                          method="POST"
                                          action="{{ route('adv.offers.destroy', $o) }}"
                                          onsubmit="return confirm('Удалить оффер «{{ $o->name }}»?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="action-btn ml-3">
                                            Удалить
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="p-4 text-gray-600" colspan="7">
                                    Офферов пока нет.
                                    <a class="text-indigo-700 underline hover:text-indigo-900"
                                       href="{{ route('adv.offers.create') }}">
                                        Создать первый →
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $list->links() }}
            </div>
        </div>
    </div>

    {{-- SortableJS для Kanban --}}
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        const token = tokenMeta ? tokenMeta.getAttribute('content') : '';

        document.querySelectorAll('#offers-kanban .kanban-col').forEach(col => {
            new Sortable(col, {
                group: 'offers',
                animation: 150,
                onEnd: async (evt) => {
                    const card = evt.item;
                    const offerId = card.getAttribute('data-offer-id');
                    const newStatus = evt.to.getAttribute('data-status');
                    try {
                        const res = await fetch(`{{ url('/adv/offers') }}/${offerId}/status`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ status: newStatus })
                        });
                        if (!res.ok) throw new Error('Bad response');
                    } catch (e) {
                        // Откат визуально — вернём карточку на место
                        evt.from.insertBefore(card, evt.from.children[evt.oldIndex] || null);
                        alert('Не удалось сохранить статус. Попробуйте ещё раз.');
                    }
                }
            });
        });
    });
    </script>
</x-app-layout>
