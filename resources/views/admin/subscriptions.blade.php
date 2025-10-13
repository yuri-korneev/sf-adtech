{{-- resources/views/admin/subscriptions.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <div class="flex items-center justify-between">
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">Админ: выданные ссылки (подписки)</h2>
      <a href="{{ route('admin.subscriptions.csv', request()->query()) }}"
         class="px-3 py-2 rounded-md shadow bg-white text-gray-900 border border-gray-300 hover:bg-gray-50">
        Экспорт CSV
      </a>
    </div>
  </x-slot>

  <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
    <div class="bg-white border rounded p-4">
      <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <div class="flex flex-col">
          <label class="text-sm text-gray-600 mb-1">Строка поиска</label>
          <input type="text" name="q" value="{{ request('q') }}" placeholder="Оффер, токен, вебмастер/email"
                 class="border rounded h-10 px-3">
        </div>
        <div class="flex flex-col">
          <label class="text-sm text-gray-600 mb-1">Активность</label>
          <select name="active" class="border rounded h-10 px-3">
            <option value="">Все</option>
            <option value="1" {{ request('active')==='1'?'selected':'' }}>Активные</option>
            <option value="0" {{ request('active')==='0'?'selected':'' }}>Неактивные</option>
          </select>
        </div>
        <div class="flex flex-col">
          <label class="text-sm text-gray-600 mb-1">Период</label>
          @php $periodVal = request('period','30d'); @endphp
          <select name="period" class="border rounded h-10 px-3">
            <option value="today"  {{ $periodVal==='today'?'selected':'' }}>Сегодня</option>
            <option value="7d"     {{ $periodVal==='7d'?'selected':'' }}>7 дней</option>
            <option value="30d"    {{ $periodVal==='30d'?'selected':'' }}>30 дней</option>
            <option value="custom" {{ $periodVal==='custom'?'selected':'' }}>По дате</option>
          </select>
        </div>
        <div class="flex flex-col">
          <label class="text-sm text-gray-600 mb-1">С даты</label>
          <input type="date" name="from" value="{{ optional($from)->format('Y-m-d') }}" class="border rounded h-10 px-3">
        </div>
        <div class="flex flex-col">
          <label class="text-sm text-gray-600 mb-1">По дату</label>
          <input type="date" name="to" value="{{ optional($to)->format('Y-m-d') }}" class="border rounded h-10 px-3">
        </div>
        <div class="self-end mt-2 md:mt-1 flex items-center gap-2 md:col-span-5">
          <button class="h-10 px-4 rounded border bg-white hover:bg-gray-50">Применить</button>
          @if(request()->query())
            <a href="{{ route('admin.subscriptions') }}" class="h-10 px-4 rounded border bg-white hover:bg-gray-50">Сбросить</a>
          @endif
        </div>
      </form>
    </div>

    <div class="mt-6 overflow-x-auto bg-white border rounded">
      <table class="w-full text-left">
        <thead class="border-b bg-gray-50">
        <tr class="text-gray-700 text-sm">
          <th class="p-2">ID</th>
          <th class="p-2">Token</th>
          <th class="p-2">Активна</th>
          <th class="p-2">Оффер</th>
          <th class="p-2">Вебмастер</th>
          <th class="p-2">Email</th>
          <th class="p-2">Создано</th>
          <th class="p-2">Обновлено</th>
        </tr>
        </thead>
        <tbody>
        @forelse($subs as $s)
          <tr class="border-t hover:bg-gray-50">
            <td class="p-2 tabular-nums">{{ $s->id }}</td>
            <td class="p-2 font-mono text-xs break-all">{{ $s->token }}</td>
            <td class="p-2">{{ $s->is_active ? 'Да' : 'Нет' }}</td>
            <td class="p-2">{{ $s->offer?->name }}</td>
            <td class="p-2">{{ $s->webmaster?->name }}</td>
            <td class="p-2">{{ $s->webmaster?->email }}</td>
            <td class="p-2 whitespace-nowrap tabular-nums">{{ optional($s->created_at)->format('d.m.Y H:i') }}</td>
            <td class="p-2 whitespace-nowrap tabular-nums">{{ optional($s->updated_at)->format('d.m.Y H:i') }}</td>
          </tr>
        @empty
          <tr><td class="p-4 text-gray-600" colspan="8">Нет данных.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-4">{{ $subs->links() }}</div>
  </div>
</x-app-layout>
