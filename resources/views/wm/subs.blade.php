<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Мои подписки</h2>
            <a
                href="{{ route('wm.offers') }}"
                class="px-3 py-2 rounded-md shadow
                       bg-white text-gray-900 border border-gray-300
                       hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300"
            >
                Найти офферы
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

            <table class="w-full text-left border">
                <thead>
                    <tr>
                        <th class="p-2">Оффер</th>
                        <th class="p-2">Моя стоимость клика</th>
                        <th class="p-2">Токен</th>
                        <th class="p-2">Персональная ссылка</th>
                        <th class="p-2">Статус</th>
                        <th class="p-2">Действие</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($subs as $s)
                    <tr class="border-t align-top">
                        <td class="p-2">{{ $s->offer?->name }}</td>

                        <td class="p-2">
                            {{ number_format((float)$s->cpc, 2, ',', ' ') }} ₽
                        </td>

                        <td class="p-2 font-mono text-xs break-all">
                            {{ $s->token }}
                        </td>

                        <td class="p-2">
                        @if($s->is_active)
                            <div class="flex items-center gap-2">
                                {{-- Поле с персональной ссылкой --}}
                                <input
                                    id="plink-{{ $s->id }}"
                                    type="text"
                                    readonly
                                    class="border p-1 w-full font-mono text-xs rounded"
                                    value="{{ url('/r/'.$s->token) }}"
                                >

                                {{-- Кнопка копирования (работает без https) --}}
                                <button type="button"
                                        class="px-2 py-1 text-sm rounded border border-gray-300 hover:bg-gray-50"
                                        data-copy-target="plink-{{ $s->id }}">
                                    Копировать
                                </button>

                                {{-- Кнопка «Проверить» — открывает целевой URL напрямую, без /r/{token} --}}
                                @if(!empty($s->offer?->target_url))
                                    <a href="{{ $s->offer->target_url }}" target="_blank" rel="noopener"
                                    class="inline-flex items-center px-2 py-1 text-sm rounded-md border border-indigo-300 text-indigo-700 hover:bg-indigo-50">
                                        Проверить
                                    </a>
                                @else
                                    <span class="text-gray-400 text-xs">нет целевого URL</span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Нажмите «Копировать», чтобы скопировать персональную ссылку</p>
                        @else
                            <span class="text-gray-500 text-sm">Подписка неактивна</span>
                        @endif
                    </td>



                        <td class="p-2">
                            @if($s->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    Активно
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-50 text-gray-700 border border-gray-200">
                                    Неактивно
                                </span>
                            @endif
                        </td>

                        <td class="p-2">
                            @if($s->is_active)
                                <form method="POST" action="{{ route('wm.unsubscribe', $s->id) }}"
                                      onsubmit="return confirm('Вы действительно хотите отписаться от оффера?');">
                                    @csrf
                                    <button
                                        class="px-3 py-1 rounded-md shadow
                                               bg-white text-red-700 border border-red-300
                                               hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-300"
                                    >
                                        Отписаться
                                    </button>
                                </form>
                            @else
                                <span class="text-gray-400 text-sm">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="p-2 text-gray-600 italic" colspan="6">Подписок пока нет</td>
                    </tr>
                @endforelse
                </tbody>
            </table>

            <div class="mt-4">{{ $subs->links() }}</div>
        </div>
    </div>

<script>
document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-copy-target]');
    if (!btn) return;

    const id = btn.getAttribute('data-copy-target');
    const input = document.getElementById(id);
    if (!input) return;

    // Попробуем современный API в безопасном контексте,
    // иначе — надёжный fallback через select + execCommand
    const copyByExec = () => {
        input.focus();
        input.select();
        try {
            const ok = document.execCommand('copy');
            if (ok) flash(btn);
            else fallbackNavigator();
        } catch { fallbackNavigator(); }
    };

    const fallbackNavigator = () => {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(input.value).then(() => flash(btn)).catch(() => {});
        }
    };

    const flash = (button) => {
        const old = button.textContent;
        button.textContent = 'Скопировано';
        button.classList.add('bg-green-50');
        setTimeout(() => { button.textContent = old; button.classList.remove('bg-green-50'); }, 900);
    };

    copyByExec();
});
</script>




</x-app-layout>
