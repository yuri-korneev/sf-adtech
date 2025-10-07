{{-- resources/views/adv/offers/edit.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Редактировать оффер: {{ $offer->name }}
            </h2>

            <a href="{{ route('adv.offers.index') }}"
               class="px-3 py-2 rounded-md shadow bg-white text-gray-900 border border-gray-300 hover:bg-gray-50">
                Мои офферы
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            {{-- Общие ошибки формы (если есть) --}}
            @if ($errors->any())
                <div class="mb-4 p-3 rounded border bg-red-50 text-red-700">
                    <div class="font-semibold mb-1">Проверьте форму:</div>
                    <ul class="list-disc list-inside text-sm">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white border rounded p-6">
                <form method="POST" action="{{ route('adv.offers.update', $offer) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    {{-- Название --}}
                    <div>
                        <label class="block text-sm text-gray-700 mb-1" for="name">Название оффера</label>
                        <input id="name" name="name" type="text" required
                               value="{{ old('name', $offer->name) }}"
                               class="w-full border rounded h-10 px-3 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        @error('name') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- CPC --}}
                    <div>
                        <label class="block text-sm text-gray-700 mb-1" for="cpc">CPC (₽)</label>
                        <input id="cpc" name="cpc" type="number" step="0.0001" min="0" required
                               value="{{ old('cpc', $offer->cpc) }}"
                               class="w-full border rounded h-10 px-3 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        @error('cpc') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Target URL --}}
                    <div>
                        <label class="block text-sm text-gray-700 mb-1" for="target_url">Целевой URL</label>
                        <input id="target_url" name="target_url" type="url" required
                               value="{{ old('target_url', $offer->target_url) }}"
                               class="w-full border rounded h-10 px-3 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        @error('target_url') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Активность --}}
                    <div class="flex items-center gap-2">
                        <input id="is_active" name="is_active" type="checkbox" value="1"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                               {{ old('is_active', $offer->is_active) ? 'checked' : '' }}>
                        <label for="is_active" class="text-sm text-gray-700">Активен</label>
                        @error('is_active') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Тематики --}}
                    <div>
                        <div class="font-semibold mb-2">Тематики</div>
                        @php
                            $selected = old('topics', $offer->topics()->pluck('id')->all());
                        @endphp
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                            @foreach($topics as $t)
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" name="topics[]" value="{{ $t->id }}"
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                           {{ in_array($t->id, $selected, true) ? 'checked' : '' }}>
                                    <span>{{ $t->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('topics.*') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Кнопки --}}
                    <div class="pt-2">
                        <button class="px-4 py-2 rounded border bg-white hover:bg-gray-50">
                            Сохранить
                        </button>
                        <a href="{{ route('adv.offers.index') }}"
                           class="ml-2 px-4 py-2 rounded border bg-white hover:bg-gray-50">
                            Отмена
                        </a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
