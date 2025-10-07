<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Новая тема</h2>
            <a href="{{ route('admin.topics.index') }}"
               class="px-3 py-2 rounded-md shadow bg-white text-gray-900 border border-gray-300 hover:bg-gray-50">
                Назад к списку
            </a>
        </div>
    </x-slot>

    <div class="py-6 max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white border rounded p-6">
            <form method="POST" action="{{ route('admin.topics.store') }}">
                @csrf

                <div class="mb-4">
                    <label class="block mb-1 text-sm text-gray-700">Название</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="border rounded w-full h-10 px-3">
                    @error('name')<div class="text-red-600 text-sm mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="mt-4">
                    <button class="px-4 py-2 rounded border bg-white hover:bg-gray-50">Создать</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
