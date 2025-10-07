<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Пользователи</h2>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="bg-green-100 text-green-800 p-2 rounded mb-3">{{ session('status') }}</div>
        @endif

        <form method="GET" class="mb-3 flex items-center gap-3">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Поиск: имя/email"
                   class="border p-2 rounded w-64">
            <select name="role" class="border p-2 rounded">
                <option value="">Любая роль</option>
                <option value="admin" {{ request('role')==='admin'?'selected':'' }}>Админ</option>
                <option value="advertiser" {{ request('role')==='advertiser'?'selected':'' }}>Рекламодатель</option>
                <option value="webmaster" {{ request('role')==='webmaster'?'selected':'' }}>Веб-мастер</option>
            </select>
            <button class="px-3 py-2 rounded bg-white border hover:bg-gray-50">Фильтровать</button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left border">
                <thead>
                    <tr>
                        <th class="p-2">ID</th>
                        <th class="p-2">Имя</th>
                        <th class="p-2">Email</th>
                        <th class="p-2">Роль</th>
                        <th class="p-2">Активен</th>
                        <th class="p-2">Действия</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($users as $u)
                    <tr class="border-t">
                        <td class="p-2">{{ $u->id }}</td>
                        <td class="p-2">{{ $u->name }}</td>
                        <td class="p-2">{{ $u->email }}</td>
                        <td class="p-2">{{ $u->role }}</td>
                        <td class="p-2">{{ $u->is_active ? 'Да' : 'Нет' }}</td>
                        <td class="p-2">
                            <form class="inline" method="POST" action="{{ route('admin.users.toggle', $u) }}">
                                @csrf
                                <button class="px-2 py-1 rounded border">
                                    {{ $u->is_active ? 'Отключить' : 'Включить' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td class="p-2" colspan="6">Нет пользователей</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $users->links() }}</div>
    </div>
</x-app-layout>
