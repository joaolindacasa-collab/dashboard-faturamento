@php
    $input = 'mt-1 block w-full bg-[#161a2c] border border-[#272c45] text-gray-200 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
    $label = 'block text-sm font-medium text-gray-300';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-white leading-tight">Novo usuário</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="panel rounded-xl p-6">
                <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="name" class="{{ $label }}">Nome</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus class="{{ $input }}">
                        @error('name')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="email" class="{{ $label }}">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required class="{{ $input }}">
                        @error('email')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="password" class="{{ $label }}">Senha</label>
                        <input id="password" name="password" type="password" required class="{{ $input }}">
                        @error('password')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="{{ $label }}">Confirmar senha</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required class="{{ $input }}">
                    </div>

                    <label class="flex items-center">
                        <input type="checkbox" name="is_admin" value="1" class="rounded border-[#272c45] bg-[#161a2c] text-indigo-600 focus:ring-indigo-500" @checked(old('is_admin'))>
                        <span class="ms-2 text-sm text-gray-300">Administrador (pode gerenciar usuários)</span>
                    </label>

                    <div class="flex items-center gap-3 pt-2">
                        <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-md">Criar</button>
                        <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-400 hover:text-gray-200">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
