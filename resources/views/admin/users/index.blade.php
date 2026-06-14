<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-white leading-tight">Usuários</h2>
            <a href="{{ route('admin.users.create') }}"
               class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-md">
                Novo usuário
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 p-3 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-sm">{{ session('status') }}</div>
            @endif

            <div class="panel rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-[#1e2235]">
                            <th class="py-3 px-4 font-medium">Nome</th>
                            <th class="py-3 px-4 font-medium">Email</th>
                            <th class="py-3 px-4 font-medium">Admin</th>
                            <th class="py-3 px-4 font-medium text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr class="border-b border-[#161a2c] last:border-0">
                                <td class="py-3 px-4 text-gray-200">{{ $user->name }}</td>
                                <td class="py-3 px-4 text-gray-400">{{ $user->email }}</td>
                                <td class="py-3 px-4 text-gray-300">{{ $user->is_admin ? 'Sim' : '—' }}</td>
                                <td class="py-3 px-4 text-right">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="text-indigo-400 hover:text-indigo-300">Editar</a>
                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline"
                                          onsubmit="return confirm('Excluir {{ $user->name }}?')">
                                        @csrf @method('DELETE')
                                        <button class="text-rose-400 hover:text-rose-300 ml-3">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $users->links() }}</div>
        </div>
    </div>
</x-app-layout>
