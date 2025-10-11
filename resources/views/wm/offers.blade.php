<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Активные офферы</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Сообщение об успехе/статусе --}}
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Ошибки валидации (например, по полю cpc) --}}
            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-3">
                    <ul class="list-disc ml-6">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <table class="w-full text-left border">
                <thead>
                    <tr>
                        <th class="p-2">Название</th>
                        <th class="p-2">Стоимость клика (оффер)</th>
                        <th class="p-2">Целевая ссылка</th>
                        <th class="p-2">Подписаться на оффер</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($offers as $o)
                    <tr class="border-t">
                        <td class="p-2">{{ $o->name }}</td>

                        {{-- ставка оффера (для справки WM) --}}
                        <td class="p-2">
                            {{ number_format((float)$o->cpc, 2, ',', ' ') }} ₽
                        </td>

                        <td class="p-2 truncate max-w-md">
                            <a class="text-indigo-700 underline hover:text-indigo-800"
                               href="{{ $o->target_url }}" target="_blank" rel="noopener">
                                {{ $o->target_url }}
                            </a>
                        </td>

                        <td class="p-2">
                            {{-- Форма подписки WM со своей ставкой --}}
                            <form method="POST" action="{{ route('wm.subscribe', $o) }}" class="flex items-center gap-2">
                                @csrf
                                <label for="cpc-{{ $o->id }}" class="text-sm text-gray-700 whitespace-nowrap">
                                    Моя стоимость клика (₽)
                                </label>
                                <input
                                    id="cpc-{{ $o->id }}"
                                    name="cpc"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    required
                                    class="w-36 px-2 py-1 rounded-md border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                                    value="{{ old('cpc') }}"
                                >
                                <button
                                    class="px-3 py-1 rounded-md shadow
                                           bg-white text-gray-900 border border-gray-300
                                           hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                >
                                    Подписаться
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="p-2 text-gray-600 italic" colspan="4">Активных офферов нет</td>
                    </tr>
                @endforelse
                </tbody>
            </table>

            <div class="mt-4">{{ $offers->links() }}</div>
        </div>
    </div>
</x-app-layout>
