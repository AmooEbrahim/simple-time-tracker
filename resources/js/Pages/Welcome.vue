<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});
</script>

<template>
    <Head title="Simple Time Tracker" />

    <div class="min-h-screen bg-white dark:bg-gray-900">
        <!-- Nav -->
        <nav class="border-b border-gray-200 dark:border-gray-700">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-2">
                    <svg class="h-8 w-8 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-lg font-semibold text-gray-900 dark:text-white">Simple Time Tracker</span>
                </div>
                <div v-if="canLogin" class="flex items-center gap-4">
                    <Link
                        :href="route('login')"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                    >
                        Log in
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        Sign up
                    </Link>
                </div>
            </div>
        </nav>

        <!-- Hero -->
        <div class="mx-auto max-w-7xl px-4 py-24 sm:px-6 sm:py-32 lg:px-8">
            <div class="text-center">
                <h1 class="text-4xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-5xl lg:text-6xl">
                    Track your time,
                    <span class="text-indigo-600 dark:text-indigo-400">simply</span>
                </h1>
                <p class="mx-auto mt-6 max-w-2xl text-lg text-gray-600 dark:text-gray-400">
                    A minimal, distraction-free time tracker. Start a timer, log your hours, and see where your time goes.
                </p>
                <div class="mt-10 flex items-center justify-center gap-4">
                    <Link
                        :href="route('login')"
                        class="rounded-lg bg-indigo-600 px-8 py-3 text-base font-semibold text-white shadow-sm hover:bg-indigo-700"
                    >
                        Get Started
                    </Link>
                    <a
                        href="#features"
                        class="rounded-lg border border-gray-300 px-8 py-3 text-base font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        Learn More
                    </a>
                </div>
            </div>
        </div>

        <!-- Features -->
        <div id="features" class="bg-gray-50 dark:bg-gray-800/50 py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Everything you need</h2>
                    <p class="mt-4 text-lg text-gray-600 dark:text-gray-400">Simple features that just work.</p>
                </div>
                <div class="mt-16 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
                            <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Live Timer</h3>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">Start and stop a real-time timer with one click. See elapsed time as you work.</p>
                    </div>

                    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
                            <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Manual Entry</h3>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">Forgot to start the timer? Add time entries manually with custom start and end times.</p>
                    </div>

                    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
                            <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Projects</h3>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">Organize your time by projects with custom colors for easy visual identification.</p>
                    </div>

                    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
                            <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Reports</h3>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">View your time broken down by project and date. Filter by week, month, or custom range.</p>
                    </div>

                    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
                            <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Edit & Delete</h3>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">Full control over your entries. Edit details or delete entries you no longer need.</p>
                    </div>

                    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
                            <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Dark Mode</h3>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">Easy on the eyes. Toggle between light and dark themes to match your preference.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- CTA -->
        <div class="py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-2xl bg-indigo-600 px-6 py-16 text-center shadow-xl sm:px-12">
                    <h2 class="text-3xl font-bold text-white sm:text-4xl">Start tracking your time today</h2>
                    <p class="mx-auto mt-4 max-w-xl text-lg text-indigo-100">Simple, fast, and focused on what matters &mdash; your time.</p>
                    <div class="mt-8">
                        <Link
                            :href="route('login')"
                            class="rounded-lg bg-white px-8 py-3 text-base font-semibold text-indigo-600 shadow-sm hover:bg-indigo-50"
                        >
                            Get Started Free
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="border-t border-gray-200 py-8 dark:border-gray-700">
            <div class="mx-auto max-w-7xl px-4 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6 lg:px-8">
                Simple Time Tracker &copy; {{ new Date().getFullYear() }}
            </div>
        </footer>
    </div>
</template>
