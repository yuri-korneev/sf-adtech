<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    @php
        // Единые классы ссылок
        $navClassesDesktop = 'border-transparent focus:border-transparent text-gray-700 hover:text-gray-900';
        $navClassesMobile  = 'border-transparent focus:border-transparent text-gray-700 hover:text-gray-900 hover:bg-gray-50';

        // Роль (slug) из пользователя
        $role = auth()->check() ? (auth()->user()->role ?? null) : null;

        // Маппинг ENG -> RU
        $roleRuMap = [
            'admin'       => 'Админ',
            'webmaster'   => 'Веб-мастер',
            'advertiser'  => 'Рекламодатель',
        ];
        $roleRu = $role ? ($roleRuMap[$role] ?? ucfirst($role)) : null;

        // Имя пользователя
        $rawName = auth()->check() ? (auth()->user()->name ?? '') : '';
        $name = trim(preg_replace('/\s+/u', ' ', (string)$rawName));
        $nameLower = mb_strtolower($name);

        // Любые «служебные» имена считаем ярлыками роли — их скрываем
        $serviceNames = [
            'admin','administrator','админ','администратор',
            'webmaster','web master','вебмастер','веб-мастер',
            'advertiser','publisher','рекламодатель'
        ];
        $isServiceName = in_array($nameLower, $serviceNames, true);

        // Показываем имя только если оно не служебное
        $showName = $name && !$isServiceName;
    @endphp

    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links (desktop) -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" class="{{ $navClassesDesktop }}">
                        {{ __('Личный кабинет') }}
                    </x-nav-link>

                    @auth
                        {{-- Админ --}}
                        @if($role === 'admin')
                            <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" class="{{ $navClassesDesktop }}">
                                {{ __('Админка') }}
                            </x-nav-link>
                            <x-nav-link :href="route('admin.users')" :active="request()->routeIs('admin.users')" class="{{ $navClassesDesktop }}">
                                {{ __('Пользователи') }}
                            </x-nav-link>
                            <x-nav-link :href="route('admin.offers')" :active="request()->routeIs('admin.offers')" class="{{ $navClassesDesktop }}">
                                {{ __('Офферы') }}
                            </x-nav-link>
                            <x-nav-link :href="route('admin.topics.index')" :active="request()->routeIs('admin.topics.*')" class="{{ $navClassesDesktop }}">
                                {{ __('Темы') }}
                            </x-nav-link>
                            <x-nav-link :href="route('admin.clicks')" :active="request()->routeIs('admin.clicks*')" class="{{ $navClassesDesktop }}">
                                {{ __('Клики') }}
                            </x-nav-link>
                            <x-nav-link :href="route('admin.subscriptions')" :active="request()->routeIs('admin.subscriptions*')" class="{{ $navClassesDesktop }}">
                                {{ __('Выданные ссылки') }}
                            </x-nav-link>
                        @endif

                        {{-- Рекламодатель --}}
                        @if($role === 'advertiser')
                            <x-nav-link :href="route('adv.dashboard')" :active="request()->routeIs('adv.dashboard')" class="{{ $navClassesDesktop }}">
                                {{ __('Рекламодатель') }}
                            </x-nav-link>
                            <x-nav-link :href="route('adv.offers.index')" :active="request()->routeIs('adv.offers.*')" class="{{ $navClassesDesktop }}">
                                {{ __('Мои офферы') }}
                            </x-nav-link>
                            <x-nav-link :href="route('adv.stats')" :active="request()->routeIs('adv.stats')" class="{{ $navClassesDesktop }}">
                                {{ __('Статистика') }}
                            </x-nav-link>
                        @endif

                        {{-- Веб-мастер --}}
                        @if($role === 'webmaster')
                            <x-nav-link :href="route('wm.dashboard')" :active="request()->routeIs('wm.dashboard')" class="{{ $navClassesDesktop }}">
                                {{ __('Веб-мастер') }}
                            </x-nav-link>
                            <x-nav-link :href="route('wm.offers')" :active="request()->routeIs('wm.offers')" class="{{ $navClassesDesktop }}">
                                {{ __('Офферы') }}
                            </x-nav-link>
                            <x-nav-link :href="route('wm.subs.index')" :active="request()->routeIs('wm.subs.*')" class="{{ $navClassesDesktop }}">
                                {{ __('Мои подписки') }}
                            </x-nav-link>
                            <x-nav-link :href="route('wm.stats')" :active="request()->routeIs('wm.stats') || request()->routeIs('wm.stats.*')" class="{{ $navClassesDesktop }}">
                                {{ __('Статистика') }}
                            </x-nav-link>
                        @endif
                    @endauth
                </div>
            </div>

            <!-- Settings Dropdown (desktop) -->
            @auth
                <div class="hidden sm:flex sm:items-center sm:ms-6">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                                <div class="flex items-center gap-2">
                                    @if($showName)
                                        <span>{{ $name }}</span>
                                    @endif
                                    @if($roleRu)
                                        <span class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                            {{ $roleRu }}
                                        </span>
                                    @endif
                                </div>
                                <div class="ms-1">
                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('Профиль') }}
                            </x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                                 onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('Выйти') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            @endauth

            <!-- Hamburger (mobile) -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu (mobile) -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" class="{{ $navClassesMobile }}">
                {{ __('Личный кабинет') }}
            </x-responsive-nav-link>

            @auth
                {{-- Админ --}}
                @if($role === 'admin')
                    <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" class="{{ $navClassesMobile }}">
                        {{ __('Админка') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.users')" :active="request()->routeIs('admin.users')" class="{{ $navClassesMobile }}">
                        {{ __('Пользователи') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.offers')" :active="request()->routeIs('admin.offers')" class="{{ $navClassesMobile }}">
                        {{ __('Офферы') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.topics.index')" :active="request()->routeIs('admin.topics.*')" class="{{ $navClassesMobile }}">
                        {{ __('Темы') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.clicks')" :active="request()->routeIs('admin.clicks*')" class="{{ $navClassesMobile }}">
                        {{ __('Клики') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.subscriptions')" :active="request()->routeIs('admin.subscriptions*')" class="{{ $navClassesMobile }}">
                        {{ __('Выданные ссылки') }}
                    </x-responsive-nav-link>
                @endif

                {{-- Рекламодатель --}}
                @if($role === 'advertiser')
                    <x-responsive-nav-link :href="route('adv.dashboard')" :active="request()->routeIs('adv.dashboard')" class="{{ $navClassesMobile }}">
                        {{ __('Рекламодатель') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('adv.offers.index')" :active="request()->routeIs('adv.offers.*')" class="{{ $navClassesMobile }}">
                        {{ __('Мои офферы') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('adv.stats')" :active="request()->routeIs('adv.stats')" class="{{ $navClassesMobile }}">
                        {{ __('Статистика') }}
                    </x-responsive-nav-link>
                @endif

                {{-- Веб-мастер --}}
                @if($role === 'webmaster')
                    <x-responsive-nav-link :href="route('wm.dashboard')" :active="request()->routeIs('wm.dashboard')" class="{{ $navClassesMobile }}">
                        {{ __('Веб-мастер') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('wm.offers')" :active="request()->routeIs('wm.offers')" class="{{ $navClassesMobile }}">
                        {{ __('Офферы') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('wm.subs.index')" :active="request()->routeIs('wm.subs.*')" class="{{ $navClassesMobile }}">
                        {{ __('Мои подписки') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('wm.stats')" :active="request()->routeIs('wm.stats') || request()->routeIs('wm.stats.*')" class="{{ $navClassesMobile }}">
                        {{ __('Статистика') }}
                    </x-responsive-nav-link>
                @endif
            @endauth
        </div>

        <!-- Responsive Settings Options -->
        @auth
            <div class="pt-4 pb-1 border-t border-gray-200">
                <div class="px-4">
                    <div class="font-medium text-base text-gray-800 flex items-center gap-2">
                        @if($showName)
                            <span>{{ $name }}</span>
                        @endif
                        @if($roleRu)
                            <span class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                {{ $roleRu }}
                            </span>
                        @endif
                    </div>
                    <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                </div>

                <div class="mt-3 space-y-1">
                    <x-responsive-nav-link :href="route('profile.edit')" class="{{ $navClassesMobile }}">
                        {{ __('Профиль') }}
                    </x-responsive-nav-link>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-responsive-nav-link :href="route('logout')"
                                               onclick="event.preventDefault(); this.closest('form').submit();"
                                               class="{{ $navClassesMobile }}">
                            {{ __('Выйти') }}
                        </x-responsive-nav-link>
                    </form>
                </div>
            </div>
        @endauth
    </div>
</nav>
