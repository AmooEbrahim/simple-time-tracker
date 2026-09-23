<script setup>
import { ref, onMounted, computed } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Clock3, FolderKanban, BarChart3, Upload, Sun, Moon, Laptop, Menu, X } from 'lucide-vue-next';

const page = usePage();

const showingNavigationDropdown = ref(false);
const theme = ref('system');

const applyTheme = (t) => {
    theme.value = t;
    localStorage.setItem('theme', t);
    const root = document.documentElement;
    if (t === 'dark') {
        root.classList.add('dark');
    } else if (t === 'light') {
        root.classList.remove('dark');
    } else {
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
        }
    }
};

const cycleTheme = () => {
    const order = ['system', 'light', 'dark'];
    const next = order[(order.indexOf(theme.value) + 1) % order.length];
    applyTheme(next);
};

const themeIcon = computed(() => {
    if (theme.value === 'light') return Sun;
    if (theme.value === 'dark') return Moon;
    return Laptop;
});

onMounted(() => {
    const saved = localStorage.getItem('theme') || 'system';
    applyTheme(saved);
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (theme.value === 'system') applyTheme('system');
    });
});

const userInitials = computed(() => {
    const name = page.props.auth?.user?.name || '';
    return name
        .split(' ')
        .filter(Boolean)
        .map((p) => p[0])
        .join('')
        .slice(0, 2)
        .toUpperCase() || '?';
});

const navItems = [
    { name: 'Timer', route: 'dashboard', icon: Clock3 },
    { name: 'Projects', route: 'projects.index', icon: FolderKanban },
    { name: 'Reports', route: 'reports', icon: BarChart3 },
    { name: 'Import', route: 'import', icon: Upload },
];
</script>

<template>
    <div class="min-h-screen bg-gradient-to-b from-slate-50 to-white dark:from-slate-950 dark:to-slate-900 text-slate-900 dark:text-slate-100">
        <nav
            class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/75 backdrop-blur-xl dark:border-slate-800/60 dark:bg-slate-950/75"
        >
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-8">
                    <Link :href="route('dashboard')" class="group flex items-center gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-fuchsia-500 text-white shadow-lg shadow-indigo-500/25 transition group-hover:scale-105">
                            <Clock3 class="h-5 w-5" :stroke-width="2.5" />
                        </span>
                        <span class="hidden text-base font-semibold tracking-tight sm:inline">
                            Tempo
                        </span>
                    </Link>

                    <div class="hidden items-center gap-1 sm:flex">
                        <Link
                            v-for="item in navItems"
                            :key="item.route"
                            :href="route(item.route)"
                            class="group relative inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                            :class="route().current(item.route)
                                ? 'text-slate-900 dark:text-white'
                                : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                        >
                            <component :is="item.icon" class="h-4 w-4" :stroke-width="2" />
                            {{ item.name }}
                            <span
                                v-if="route().current(item.route)"
                                class="absolute inset-x-2 -bottom-[17px] h-0.5 rounded-full bg-gradient-to-r from-indigo-500 to-fuchsia-500"
                            />
                        </Link>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="cycleTheme"
                        class="hidden h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white sm:inline-flex"
                        :title="`Theme: ${theme}`"
                    >
                        <component :is="themeIcon" class="h-4 w-4" :stroke-width="2" />
                    </button>

                    <div class="hidden sm:block">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800"
                                >
                                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-br from-slate-700 to-slate-900 text-xs font-semibold text-white dark:from-slate-200 dark:to-white dark:text-slate-900">
                                        {{ userInitials }}
                                    </span>
                                    <span class="hidden md:inline">{{ page.props.auth.user.name }}</span>
                                </button>
                            </template>

                            <template #content>
                                <DropdownLink :href="route('profile.edit')">
                                    Profile
                                </DropdownLink>
                                <DropdownLink :href="route('logout')" method="post" as="button">
                                    Log Out
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>

                    <button
                        @click="showingNavigationDropdown = !showingNavigationDropdown"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 sm:hidden"
                    >
                        <component :is="showingNavigationDropdown ? X : Menu" class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div
                    v-if="showingNavigationDropdown"
                    class="border-t border-slate-200 bg-white px-4 py-3 sm:hidden dark:border-slate-800 dark:bg-slate-950"
                >
                    <div class="space-y-1">
                        <Link
                            v-for="item in navItems"
                            :key="item.route"
                            :href="route(item.route)"
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium"
                            :class="route().current(item.route)
                                ? 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-white'
                                : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/50'"
                        >
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.name }}
                        </Link>
                    </div>
                    <div class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-800">
                        <div class="px-3 pb-2 text-xs text-slate-500 dark:text-slate-400">
                            {{ page.props.auth.user.email }}
                        </div>
                        <button
                            @click="cycleTheme"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/50"
                        >
                            <component :is="themeIcon" class="h-4 w-4" />
                            Theme: {{ theme }}
                        </button>
                        <Link
                            :href="route('profile.edit')"
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/50"
                        >
                            Profile
                        </Link>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/50"
                        >
                            Log Out
                        </Link>
                    </div>
                </div>
            </transition>
        </nav>

        <header v-if="$slots.header" class="border-b border-slate-200/70 dark:border-slate-800/60">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <main>
            <slot />
        </main>
    </div>
</template>
