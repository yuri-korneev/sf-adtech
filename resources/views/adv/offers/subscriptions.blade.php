<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Подписки на оффер: {{ $offer->name }}
            </h2>
            <div class="space-x-3">
                <a href="{{ route('adv.offers.index') }}"
                   class="px-3 py-2 rounded-md shadow bg-white text-gray-900 border border-gray-300 hover:bg-gray-50">
                    Мои офферы
                </a>
                <a href="{{ route('adv.stats') }}"
                   class="px-3 py-2 rounded-md shadow bg-white text-gray-900 border border-gray-300 hover:bg-gray-50">
                    Статистика
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <table class="w-full text-left border">
                <thead>
                    <tr>
                        <th class="p-2">Веб-мастер</th>
                        <th class="p-2">Токен</th>
                        <th class="p-2">Активна</th>
                        <th class="p-2">Клики (всего)</th>
                        <th class="p-2">Клики (валидные)</th>
                        <th class="p-2">Создана</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($subs as $s)
                    <tr class="border-t">
                        <td class="p-2">
                            {{ $s->webmaster?->name ?? '—' }}
                            <div class="text-xs text-gray-500">{{ $s->webmaster?->email }}</div>
                        </td>
                        <td class="p-2 font-mono text-xs break-all">{{ $s->token }}</td>
                        <td class="p-2">{{ $s->is_active ? 'Да' : 'Нет' }}</td>
                        <td class="p-2">{{ $s->clicks_count }}</td>
                        <td class="p-2">{{ $s->valid_clicks_count }}</td>
                        <td class="p-2">{{ optional($s->created_at)->translatedFormat('d MMM Y, H:i') }}</td>
                    </tr>
                @empty
                    <tr><td class="p-2" colspan="6">Подписок пока нет</td></tr>
                @endforelse
                </tbody>
            </table>

            <div class="mt-4">{{ $subs->links() }}</div>
        </div>
    </div>
</x-app-layout>
