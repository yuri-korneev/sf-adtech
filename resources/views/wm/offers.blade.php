<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Active Offers</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <table class="w-full text-left border">
                <thead>
                    <tr>
                        <th class="p-2">Name</th>
                        <th class="p-2">CPC</th>
                        <th class="p-2">Target</th>
                        <th class="p-2">Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($offers as $o)
                    <tr class="border-t">
                        <td class="p-2">{{ $o->name }}</td>
                        <td class="p-2">{{ $o->cpc }}</td>
                        <td class="p-2 truncate max-w-md">
                            <a class="text-indigo-700 underline hover:text-indigo-800"
                               href="{{ $o->target_url }}" target="_blank" rel="noopener">
                                {{ $o->target_url }}
                            </a>
                        </td>
                        <td class="p-2">
                            <form method="POST" action="{{ route('wm.subscribe', $o) }}">
                                @csrf
                                <button
                                    class="px-3 py-1 rounded-md shadow
                                           bg-white text-gray-900 border border-gray-300
                                           hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                >
                                    Subscribe
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td class="p-2" colspan="4">No active offers</td></tr>
                @endforelse
                </tbody>
            </table>

            <div class="mt-4">{{ $offers->links() }}</div>
        </div>
    </div>
</x-app-layout>
