{{-- resources/views/wm/offers.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Активные офферы</h2>
    </x-slot>

    @php
        // Комиссия системы (0..1). По умолчанию 20%.
        $commission = (float) config('sf.commission', 0.20);
        $wmPct = 1 - $commission;
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Сообщение об успехе/статусе --}}
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Общие ошибки (если есть) --}}
            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-3">
                    <ul class="list-disc ml-6">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white border rounded overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="border-b bg-gray-50">
                        <tr class="text-gray-700 text-sm">
                            <th class="p-3">Название</th>
                            <th class="p-3">Стоимость клика (₽)</th>
                            <th class="p-3">Выплата WM за клик (₽)</th>
                            <th class="p-3">Целевой URL</th>
                            <th class="p-3 text-center">Действие</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($offers as $o)
                        @php
                            $cpc = (float) $o->cpc;
                            $wmPayout = $cpc * $wmPct;
                        @endphp
                        <tr class="border-t hover:bg-gray-50">
                            {{-- Название --}}
                            <td class="p-3 align-top">
                                <div class="font-medium text-gray-900">{{ $o->name }}</div>
                                <div class="text-xs text-gray-500">ID: {{ $o->id }}</div>
                            </td>

                            {{-- CPC оффера (источник истины по ТЗ) --}}
                            <td class="p-3 whitespace-nowrap align-top">
                                {{ number_format($cpc, 2, ',', ' ') }} ₽
                            </td>

                            {{-- Выплата WM за валидный клик --}}
                            <td class="p-3 whitespace-nowrap align-top">
                                {{ number_format($wmPayout, 2, ',', ' ') }} ₽
                                <div class="text-xs text-gray-500">
                                    = ставка × (1 − комиссия {{ (int)round($commission*100) }}%)
                                </div>
                            </td>

                            {{-- URL --}}
                            <td class="p-3 align-top">
                                <a class="text-indigo-700 underline hover:text-indigo-900 break-words"
                                   href="{{ $o->target_url }}" target="_blank" rel="noopener">
                                    {{ $o->target_url }}
                                </a>
                            </td>

                            {{-- Подписаться (без поля "моя ставка") --}}
                            <td class="p-3 align-top text-center">
                                <form method="POST" action="{{ route('wm.subscribe', $o) }}">
                                    @csrf
                                    <button
                                        class="px-3 py-2 rounded-md shadow
                                               bg-white text-gray-900 border border-gray-300
                                               hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-300">
                                        Подписаться
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="p-4 text-gray-600 italic" colspan="5">Активных офферов нет.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $offers->links() }}</div>
        </div>
    </div>
</x-app-layout>
