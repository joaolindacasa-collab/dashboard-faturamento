@php
    $navLink = 'inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition';
    $active = 'border-indigo-400 text-white';
    $inactive = 'border-transparent text-gray-400 hover:text-gray-200 hover:border-gray-600';
@endphp

<nav x-data="{ open: false }" class="bg-[#0f111e] border-b border-[#1e2235]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-200" />
                    </a>
                </div>

                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <a href="{{ route('dashboard') }}" class="{{ $navLink }} {{ request()->routeIs('dashboard') ? $active : $inactive }}">Dashboard</a>
                    <a href="{{ route('status') }}" class="{{ $navLink }} {{ request()->routeIs('status') ? $active : $inactive }}">Status</a>
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.users.index') }}" class="{{ $navLink }} {{ request()->routeIs('admin.users.*') ? $active : $inactive }}">Usuários</a>
                    @endif
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-300 bg-[#161a2c] border border-[#272c45] hover:text-white focus:outline-none transition">
                            <div>{{ Auth::user()->name }}</div>
                            <svg class="ms-1 fill-current h-4 w-4" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">{{ __('Profile') }}</x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Sair</x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-200 hover:bg-[#161a2c] focus:outline-none transition">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden bg-[#0f111e] border-t border-[#1e2235]">
        <div class="pt-2 pb-3 space-y-1">
            <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-base {{ request()->routeIs('dashboard') ? 'text-white bg-[#161a2c]' : 'text-gray-400 hover:text-gray-200' }}">Dashboard</a>
            <a href="{{ route('status') }}" class="block px-4 py-2 text-base {{ request()->routeIs('status') ? 'text-white bg-[#161a2c]' : 'text-gray-400 hover:text-gray-200' }}">Status</a>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.users.index') }}" class="block px-4 py-2 text-base {{ request()->routeIs('admin.users.*') ? 'text-white bg-[#161a2c]' : 'text-gray-400 hover:text-gray-200' }}">Usuários</a>
            @endif
        </div>
        <div class="pt-4 pb-1 border-t border-[#1e2235]">
            <div class="px-4">
                <div class="font-medium text-base text-gray-200">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>
            <div class="mt-3 space-y-1">
                <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-base text-gray-400 hover:text-gray-200">{{ __('Profile') }}</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <a href="{{ route('logout') }}" onclick="event.preventDefault(); this.closest('form').submit();" class="block px-4 py-2 text-base text-gray-400 hover:text-gray-200">Sair</a>
                </form>
            </div>
        </div>
    </div>
</nav>
