{{-- resources/views/admin/topics/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Админ: темы</h2>

            <a href="{{ route('admin.topics.create') }}"
               class="px-3 py-2 rounded-md shadow bg-white text-gray-900 border border-gray-300 hover:bg-gray-50">
                Создать тему
            </a>
        </div>
    </x-slot>

    <style>
        #topicsTable thead th { text-align: center !important; vertical-align: middle !important; }
        #topicsTable tbody td { text-align: center !important; vertical-align: middle !important; }

        #topicsTable .action-link {
            color: #1d4ed8; text-decoration: underline; transition: color .15s ease;
        }
        #topicsTable .action-link:hover { color: #1e3a8a !important; }

        #topicsTable .action-btn {
            color: #b91c1c; text-decoration: underline; background: transparent; border: none;
            padding: 0; cursor: pointer; transition: color .15s ease;
        }
        #topicsTable .action-btn:hover { color: #7f1d1d !important; }
        #topicsTable .action-btn[disabled] { opacity: .5; cursor: not-allowed; }
    </style>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="bg-green-100 border border-green-200 text-green-800 p-2 rounded mb-3">
                {{ session('status') }}
            </div>
        @endif
        @if (session('error'))
            <div class="bg-red-100 border border-red-200 text-red-800 p-2 rounded mb-3">
                {{ session('error') }}
            </div>
        @endif

        <form method="GET" class="mb-4 flex flex-wrap items-center gap-3">
            <input type="text" name="q" value="{{ $q }}" placeholder="Название темы..."
                   class="border rounded h-10 px-3 w-64">
            <button class="px-4 py-2 rounded border bg-white hover:bg-gray-50">Поиск</button>
            @if($q !== '')
                <a href="{{ route('admin.topics.index') }}"
                   class="px-4 py-2 rounded border bg-white hover:bg-gray-50">Сбросить</a>
            @endif
        </form>

        <div class="overflow-x-auto bg-white border rounded">
            <table id="topicsTable" class="w-full">
                <thead class="border-b bg-gray-50">
                <tr class="text-gray-700 text-sm">
                    <th class="p-3">ID</th>
                    <th class="p-3">Название</th>
                    <th class="p-3">Привязано офферов</th>
                    <th class="p-3">Действия</th>
                </tr>
                </thead>
                <tbody>
                @forelse($topics as $t)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="p-3">{{ $t->id }}</td>
                        <td class="p-3">
                            <div class="font-medium text-gray-900">{{ $t->name }}</div>
                        </td>

                        <td class="p-3">
                            <a href="{{ route('admin.offers', ['topic' => $t->id]) }}"
                               class="inline-flex items-center justify-center min-w-[3rem] px-2 py-1 rounded bg-gray-100 text-gray-800 hover:bg-gray-200">
                                {{ $t->offers_count ?? 0 }}
                            </a>
                        </td>

                        <td class="p-3 whitespace-nowrap">
                            <a class="action-link" href="{{ route('admin.topics.edit', $t) }}">Редактировать</a>

                            @if(($t->offers_count ?? 0) > 0)
                                <button type="button"
                                        class="action-btn ml-3"
                                        disabled
                                        title="Нельзя удалить: к теме привязаны офферы">
                                    Удалить
                                </button>
                            @else
                                <form class="inline" method="POST" action="{{ route('admin.topics.destroy', $t) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="action-btn ml-3"
                                            onclick="return confirm('Удалить тему «{{ $t->name }}»?');">
                                        Удалить
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="p-4 text-gray-600" colspan="4">Тем пока нет.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $topics->links() }}</div>
    </div>
</x-app-layout>
