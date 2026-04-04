<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    range: String,
    startDate: String,
    endDate: String,
    selectedProjectId: [Number, String],
    totalSeconds: Number,
    groupedByProject: Array,
    groupedByDate: Array,
    groupedByTag: Array,
    projects: Array,
});

const filterRange = ref(props.range);
const filterStartDate = ref(props.startDate);
const filterEndDate = ref(props.endDate);
const filterProjectId = ref(props.selectedProjectId || '');

const formatDuration = (seconds) => {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
};

const totalDisplay = computed(() => formatDuration(props.totalSeconds));

const applyFilters = () => {
    router.get(route('reports'), {
        range: filterRange.value,
        start: filterStartDate.value,
        end: filterEndDate.value,
        project_id: filterProjectId.value || null,
    }, { preserveScroll: true });
};

const setRange = (range) => {
    filterRange.value = range;
    router.get(route('reports'), {
        range,
        start: filterStartDate.value,
        end: filterEndDate.value,
        project_id: filterProjectId.value || null,
    }, { preserveScroll: true });
};

const exportReport = () => {
    const params = new URLSearchParams({
        start: filterStartDate.value,
        end: filterEndDate.value,
    });
    if (filterProjectId.value) {
        params.append('project_id', filterProjectId.value);
    }
    window.open(`${route('reports.export')}?${params.toString()}`, '_blank');
};

const getProjectColor = (project) => project?.color || '#6366f1';
const getProjectName = (project) => project?.name || 'No Project';

const maxProjectSeconds = computed(() => {
    return Math.max(...props.groupedByProject.map(g => g.total_seconds), 1);
});

const maxDateSeconds = computed(() => {
    return Math.max(...props.groupedByDate.map(g => g.total_seconds), 1);
});

const maxTagSeconds = computed(() => {
    return Math.max(...props.groupedByTag.map(g => g.total_seconds), 1);
});
</script>

<template>
    <Head title="Reports" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Reports
                </h2>
                <button
                    @click="exportReport"
                    class="flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="mb-6 rounded-xl bg-white dark:bg-gray-800 p-4 shadow-sm dark:shadow-gray-900/20">
                    <div class="flex flex-wrap items-end gap-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                v-for="r in ['today', 'week', 'month', 'year', 'custom']"
                                :key="r"
                                @click="setRange(r)"
                                class="rounded-lg px-3 py-1.5 text-xs font-medium capitalize"
                                :class="filterRange === r ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600'"
                            >
                                {{ r }}
                            </button>
                        </div>
                        <div v-if="filterRange === 'custom'" class="flex items-center gap-2">
                            <input v-model="filterStartDate" type="date" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm" />
                            <span class="text-gray-400">to</span>
                            <input v-model="filterEndDate" type="date" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm" />
                        </div>
                        <select v-model="filterProjectId" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                            <option value="">All Projects</option>
                            <option v-for="project in projects" :key="project.id" :value="project.id">
                                {{ project.name }}
                            </option>
                        </select>
                        <button
                            @click="applyFilters"
                            class="rounded-lg bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            Apply
                        </button>
                    </div>
                </div>

                <!-- Summary Card -->
                <div class="mb-6 rounded-xl bg-white dark:bg-gray-800 p-6 shadow-sm dark:shadow-gray-900/20">
                    <div class="grid grid-cols-2 gap-6 sm:grid-cols-4">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Time</p>
                            <p class="mt-1 font-mono text-2xl font-semibold text-gray-800 dark:text-gray-200">{{ totalDisplay }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Seconds</p>
                            <p class="mt-1 font-mono text-2xl font-semibold text-gray-800 dark:text-gray-200">{{ totalSeconds.toLocaleString() }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Projects</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-800 dark:text-gray-200">{{ groupedByProject.length }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Entries</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-800 dark:text-gray-200">{{ groupedByProject.reduce((sum, g) => sum + g.entry_count, 0) }}</p>
                        </div>
                    </div>
                </div>

                <!-- By Project -->
                <div class="mb-6 rounded-xl bg-white dark:bg-gray-800 p-6 shadow-sm dark:shadow-gray-900/20">
                    <h3 class="mb-4 text-sm font-medium text-gray-700 dark:text-gray-300">By Project</h3>
                    <div class="space-y-3">
                        <div
                            v-for="group in groupedByProject"
                            :key="group.project?.id || 'none'"
                        >
                            <div class="mb-1 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="h-3 w-3 rounded-full"
                                        :style="{ backgroundColor: getProjectColor(group.project) }"
                                    ></div>
                                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ getProjectName(group.project) }}</span>
                                </div>
                                <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                                    <span>{{ formatDuration(group.total_seconds) }}</span>
                                    <span class="text-xs">{{ group.entry_count }} entries</span>
                                </div>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :style="{
                                        width: `${(group.total_seconds / maxProjectSeconds) * 100}%`,
                                        backgroundColor: getProjectColor(group.project),
                                    }"
                                ></div>
                            </div>
                        </div>
                        <div v-if="groupedByProject.length === 0" class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            No data for this period.
                        </div>
                    </div>
                </div>

                <!-- By Tag -->
                <div class="mb-6 rounded-xl bg-white dark:bg-gray-800 p-6 shadow-sm dark:shadow-gray-900/20">
                    <h3 class="mb-4 text-sm font-medium text-gray-700 dark:text-gray-300">By Tag</h3>
                    <div class="space-y-3">
                        <div
                            v-for="group in groupedByTag"
                            :key="group.tag?.id || 'no_tag'"
                        >
                            <div class="mb-1 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full bg-indigo-50 dark:bg-indigo-900/20 px-2 py-0.5 text-xs font-medium text-indigo-600 dark:text-indigo-400">
                                        {{ group.tag?.name || 'No Tag' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                                    <span>{{ formatDuration(group.total_seconds) }}</span>
                                    <span class="text-xs">{{ group.entry_count }} entries</span>
                                </div>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                <div
                                    class="h-full rounded-full bg-indigo-500 transition-all"
                                    :style="{ width: `${(group.total_seconds / maxTagSeconds) * 100}%` }"
                                ></div>
                            </div>
                        </div>
                        <div v-if="groupedByTag.length === 0" class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            No data for this period.
                        </div>
                    </div>
                </div>

                <!-- By Date -->
                <div class="rounded-xl bg-white dark:bg-gray-800 p-6 shadow-sm dark:shadow-gray-900/20">
                    <h3 class="mb-4 text-sm font-medium text-gray-700 dark:text-gray-300">By Date</h3>
                    <div class="space-y-3">
                        <div
                            v-for="group in groupedByDate"
                            :key="group.date"
                        >
                            <div class="mb-1 flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ new Date(group.date).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }) }}</span>
                                <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                                    <span>{{ formatDuration(group.total_seconds) }}</span>
                                    <span class="text-xs">{{ group.entry_count }} entries</span>
                                </div>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                <div
                                    class="h-full rounded-full bg-indigo-500 transition-all"
                                    :style="{ width: `${(group.total_seconds / maxDateSeconds) * 100}%` }"
                                ></div>
                            </div>
                        </div>
                        <div v-if="groupedByDate.length === 0" class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            No data for this period.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
