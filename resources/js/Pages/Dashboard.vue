<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    groupedDays: Array,
    hasMore: Boolean,
    nextCursor: String,
    totalSeconds: Number,
    projects: Array,
    tags: Array,
});

const runningTimer = computed(() => {
    for (const day of props.groupedDays) {
        const timer = day.entries.find(e => !e.ended_at);
        if (timer) return timer;
    }
    return null;
});

const timerDisplay = ref('00:00:00');
let timerInterval = null;

const formatDuration = (seconds) => {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
};

const formatDurationShort = (seconds) => {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
};

const formatTime = (dateStr) => {
    if (!dateStr) return '--:--';
    return new Date(dateStr).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
};

onMounted(() => {
    if (runningTimer.value) {
        const startedAt = new Date(runningTimer.value.started_at).getTime();
        timerInterval = setInterval(() => {
            const elapsed = Math.floor((Date.now() - startedAt) / 1000);
            timerDisplay.value = formatDuration(elapsed);
        }, 1000);
    }
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});

const timerForm = useForm({
    description: '',
    project_id: null,
    tag_names: [],
});

const timerTags = ref([]);
const timerTagInput = ref('');

const addTimerTag = () => {
    const tag = timerTagInput.value.trim();
    if (tag && !timerTags.value.includes(tag)) {
        timerTags.value.push(tag);
    }
    timerTagInput.value = '';
};

const removeTimerTag = (tag) => {
    timerTags.value = timerTags.value.filter(t => t !== tag);
};

const startTimer = () => {
    timerForm.tag_names = timerTags.value;
    timerForm.post(route('time-entries.store'), {
        preserveScroll: true,
        onSuccess: () => {
            timerForm.reset();
            timerTags.value = [];
            timerTagInput.value = '';
            location.reload();
        },
    });
};

const stopTimer = (entry) => {
    router.post(route('time-entries.stop', entry.id), {}, {
        preserveScroll: true,
        onSuccess: () => location.reload(),
    });
};

const restartTimer = (entry) => {
    router.post(route('time-entries.restart', entry.id), {}, {
        preserveScroll: true,
        onSuccess: () => location.reload(),
    });
};

const deleteEntry = (entry) => {
    if (confirm('Are you sure you want to delete this time entry?')) {
        router.delete(route('time-entries.destroy', entry.id), {
            preserveScroll: true,
        });
    }
};

const showManualEntry = ref(false);
const manualForm = useForm({
    description: '',
    project_id: null,
    started_at: '',
    ended_at: '',
    tag_names: [],
});

const manualTags = ref([]);
const newTagInput = ref('');

const addManualTag = () => {
    const tag = newTagInput.value.trim();
    if (tag && !manualTags.value.includes(tag)) {
        manualTags.value.push(tag);
    }
    newTagInput.value = '';
};

const removeManualTag = (tag) => {
    manualTags.value = manualTags.value.filter(t => t !== tag);
};

const submitManualEntry = () => {
    manualForm.tag_names = manualTags.value;
    manualForm.started_at = toUtcDatetime(manualForm.started_at);
    manualForm.ended_at = toUtcDatetime(manualForm.ended_at);
    manualForm.post(route('time-entries.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showManualEntry.value = false;
            manualForm.reset();
            manualTags.value = [];
        },
    });
};

const editEntry = ref(null);
const editForm = useForm({
    description: '',
    project_id: null,
    started_at: '',
    ended_at: '',
    tag_names: [],
});

const editTags = ref([]);
const editTagInput = ref('');

const openEdit = (entry) => {
    editEntry.value = entry;
    editForm.description = entry.description || '';
    editForm.project_id = entry.project_id;
    editForm.started_at = toLocalDatetime(entry.started_at);
    editForm.ended_at = entry.ended_at ? toLocalDatetime(entry.ended_at) : '';
    editTags.value = (entry.tags || []).map(t => t.name);
    editTagInput.value = '';
};

const addEditTag = () => {
    const tag = editTagInput.value.trim();
    if (tag && !editTags.value.includes(tag)) {
        editTags.value.push(tag);
    }
    editTagInput.value = '';
};

const removeEditTag = (tag) => {
    editTags.value = editTags.value.filter(t => t !== tag);
};

const submitEdit = () => {
    editForm.tag_names = editTags.value;
    editForm.started_at = toUtcDatetime(editForm.started_at);
    editForm.ended_at = editForm.ended_at ? toUtcDatetime(editForm.ended_at) : null;
    editForm.put(route('time-entries.update', editEntry.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editEntry.value = null;
        },
    });
};

const loadMore = () => {
    if (!props.hasMore || !props.nextCursor) return;
    router.get(route('dashboard'), { cursor: props.nextCursor }, {
        preserveScroll: true,
        preserveState: true,
        only: ['groupedDays', 'hasMore', 'nextCursor', 'totalSeconds'],
    });
};

const getProjectColor = (project) => project?.color || '#6366f1';
const getProjectName = (project) => project?.name || 'No Project';

const hexToRgba = (hex, alpha) => {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
};

const getProjectGradient = (project) => {
    const color = getProjectColor(project);
    return `linear-gradient(to right, ${hexToRgba(color, 0.25)} 0%, ${hexToRgba(color, 0.03)} 100%)`;
};

const toLocalDatetime = (utcStr) => {
    if (!utcStr) return '';
    const d = new Date(utcStr);
    const pad = (n) => n.toString().padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const toUtcDatetime = (localStr) => {
    if (!localStr) return null;
    const d = new Date(localStr);
    return d.toISOString().replace('T', ' ').replace('Z', '');
};
</script>

<template>
    <Head title="Time Tracker" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Time Tracker
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <!-- Timer Bar -->
                <div class="mb-6 rounded-xl bg-white dark:bg-gray-800 p-4 shadow-sm dark:shadow-gray-900/20">
                    <div class="flex items-center gap-4">
                        <div class="flex-1">
                            <input
                                v-model="timerForm.description"
                                type="text"
                                placeholder="What are you working on?"
                                class="w-full border-0 border-b border-gray-200 dark:border-gray-700 px-3 py-2 text-sm placeholder-gray-400 dark:placeholder-gray-500 focus:border-indigo-500 focus:ring-0"
                                @keyup.enter="startTimer"
                            />
                        </div>
                        <select
                            v-model="timerForm.project_id"
                            class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                        >
                            <option :value="null">No Project</option>
                            <option v-for="project in projects" :key="project.id" :value="project.id">
                                {{ project.name }}
                            </option>
                        </select>
                        <button
                            v-if="!runningTimer"
                            @click="startTimer"
                            class="rounded-lg bg-indigo-600 px-6 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            Start
                        </button>
                        <button
                            v-else
                            @click="stopTimer(runningTimer)"
                            class="rounded-lg bg-red-600 px-6 py-2 text-sm font-medium text-white hover:bg-red-700"
                        >
                            Stop
                        </button>
                        <span v-if="runningTimer" class="min-w-[80px] text-right font-mono text-lg font-semibold text-indigo-600 dark:text-indigo-400">
                            {{ timerDisplay }}
                        </span>
                    </div>
                    <!-- Tags in timer bar -->
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span
                            v-for="tag in timerTags"
                            :key="tag"
                            class="inline-flex items-center gap-1 rounded-full bg-indigo-100 dark:bg-indigo-900/30 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300"
                        >
                            {{ tag }}
                            <button @click="removeTimerTag(tag)" class="hover:text-indigo-900 dark:hover:text-indigo-100">&times;</button>
                        </span>
                        <input
                            v-model="timerTagInput"
                            type="text"
                            placeholder="Add tag..."
                            class="rounded-md border-0 border-b border-gray-200 dark:border-gray-700 bg-transparent px-2 py-0.5 text-xs placeholder-gray-400 focus:border-indigo-500 focus:ring-0"
                            @keyup.enter.prevent="addTimerTag"
                        />
                    </div>
                </div>

                <!-- Manual Entry Toggle -->
                <button
                    @click="showManualEntry = !showManualEntry"
                    class="mb-4 text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-700"
                >
                    {{ showManualEntry ? 'Hide manual entry' : '+ Add manual entry' }}
                </button>

                <!-- Manual Entry Form -->
                <div v-if="showManualEntry" class="mb-6 rounded-xl bg-white dark:bg-gray-800 p-4 shadow-sm dark:shadow-gray-900/20">
                    <h3 class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">Manual Time Entry</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <input
                            v-model="manualForm.description"
                            type="text"
                            placeholder="Description"
                            class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                        />
                        <select v-model="manualForm.project_id" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                            <option :value="null">No Project</option>
                            <option v-for="project in projects" :key="project.id" :value="project.id">
                                {{ project.name }}
                            </option>
                        </select>
                        <input v-model="manualForm.started_at" type="datetime-local" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm" />
                        <input v-model="manualForm.ended_at" type="datetime-local" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm" />
                        <button
                            @click="submitManualEntry"
                            :disabled="manualForm.processing"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            Add Entry
                        </button>
                    </div>
                    <div class="mt-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                v-for="tag in manualTags"
                                :key="tag"
                                class="inline-flex items-center gap-1 rounded-full bg-indigo-100 dark:bg-indigo-900/30 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300"
                            >
                                {{ tag }}
                                <button @click="removeManualTag(tag)" class="hover:text-indigo-900 dark:hover:text-indigo-100">&times;</button>
                            </span>
                            <input
                                v-model="newTagInput"
                                type="text"
                                placeholder="Add tag..."
                                class="rounded-md border-0 border-b border-gray-200 dark:border-gray-700 bg-transparent px-2 py-0.5 text-xs placeholder-gray-400 focus:border-indigo-500 focus:ring-0"
                                @keyup.enter.prevent="addManualTag"
                            />
                        </div>
                    </div>
                </div>

                <!-- Edit Modal -->
                <div v-if="editEntry" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div class="w-full max-w-lg rounded-xl bg-white dark:bg-gray-800 p-6 shadow-xl dark:shadow-black/30">
                        <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Edit Time Entry</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                                <input
                                    v-model="editForm.description"
                                    type="text"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Project</label>
                                <select v-model="editForm.project_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                                    <option :value="null">No Project</option>
                                    <option v-for="project in projects" :key="project.id" :value="project.id">
                                        {{ project.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Start</label>
                                    <input v-model="editForm.started_at" type="datetime-local" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">End</label>
                                    <input v-model="editForm.ended_at" type="datetime-local" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm" />
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Tags</label>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        v-for="tag in editTags"
                                        :key="tag"
                                        class="inline-flex items-center gap-1 rounded-full bg-indigo-100 dark:bg-indigo-900/30 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300"
                                    >
                                        {{ tag }}
                                        <button @click="removeEditTag(tag)" class="hover:text-indigo-900 dark:hover:text-indigo-100">&times;</button>
                                    </span>
                                    <input
                                        v-model="editTagInput"
                                        type="text"
                                        placeholder="Add tag..."
                                        class="rounded-md border-0 border-b border-gray-200 dark:border-gray-700 bg-transparent px-2 py-0.5 text-xs placeholder-gray-400 focus:border-indigo-500 focus:ring-0"
                                        @keyup.enter.prevent="addEditTag"
                                    />
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end gap-3">
                            <button
                                @click="editEntry = null"
                                class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                Cancel
                            </button>
                            <button
                                @click="submitEdit"
                                :disabled="editForm.processing"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Save
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Entries Grouped by Day with Week Headers -->
                <div class="space-y-4">
                    <template v-for="(day, dayIndex) in groupedDays" :key="day.date">
                        <!-- Week Header -->
                        <div v-if="day.show_week_header" class="flex items-center gap-3 py-2">
                            <div class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></div>
                            <span class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                Week: {{ day.week_start }} - {{ day.week_end }}
                            </span>
                        </div>

                        <!-- Day Header -->
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                <span v-if="day.is_today" class="text-indigo-600 dark:text-indigo-400">Today</span>
                                <span v-else-if="day.is_yesterday">Yesterday</span>
                                <span v-else>{{ day.day_label }}</span>
                            </h3>
                            <span class="text-xs font-mono text-gray-500 dark:text-gray-400">
                                {{ formatDurationShort(day.total_seconds) }}
                            </span>
                        </div>

                        <!-- Day Entries -->
                        <div class="space-y-1">
                            <div
                                v-for="entry in day.entries"
                                :key="entry.id"
                                class="group relative overflow-hidden rounded-lg bg-white dark:bg-gray-800 shadow-sm dark:shadow-gray-900/20 transition-shadow hover:shadow-md"
                            >
                                <!-- Project gradient background -->
                                <div
                                    v-if="entry.project"
                                    class="absolute inset-y-0 left-0 w-[30%] pointer-events-none"
                                    :style="{ background: getProjectGradient(entry.project) }"
                                ></div>

                                <div class="relative flex items-center justify-between p-4">
                                    <div class="flex min-w-0 flex-1 items-center gap-3">
                                        <div
                                            class="h-3 w-3 shrink-0 rounded-full"
                                            :style="{ backgroundColor: getProjectColor(entry.project) }"
                                        ></div>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ entry.description || 'No description' }}
                                            </p>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ getProjectName(entry.project) }}
                                                </p>
                                                <template v-if="entry.tags && entry.tags.length">
                                                    <span
                                                        v-for="tag in entry.tags"
                                                        :key="tag.id"
                                                        class="rounded-full bg-indigo-50 dark:bg-indigo-900/20 px-1.5 py-0.5 text-[10px] font-medium text-indigo-600 dark:text-indigo-400"
                                                    >
                                                        {{ tag.name }}
                                                    </span>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="text-right">
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ formatTime(entry.started_at) }} - {{ formatTime(entry.ended_at) }}
                                            </p>
                                            <p class="font-mono text-sm font-semibold text-gray-800 dark:text-gray-200">
                                                {{ entry.formatted_duration || formatDuration(entry.duration_seconds) }}
                                            </p>
                                        </div>
                                        <div class="hidden gap-1 group-hover:flex">
                                            <button
                                                v-if="entry.ended_at"
                                                @click="restartTimer(entry)"
                                                title="Restart with this project & description"
                                                class="rounded p-1 text-gray-400 dark:text-gray-500 hover:text-green-600"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                            </button>
                                            <button
                                                v-if="entry.ended_at"
                                                @click="openEdit(entry)"
                                                class="rounded p-1 text-gray-400 dark:text-gray-500 hover:text-indigo-600"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                v-if="entry.ended_at === null"
                                                @click="stopTimer(entry)"
                                                class="rounded p-1 text-gray-400 dark:text-gray-500 hover:text-red-600"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                                                </svg>
                                            </button>
                                            <button
                                                @click="deleteEntry(entry)"
                                                class="rounded p-1 text-gray-400 dark:text-gray-500 hover:text-red-600"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Load More -->
                    <div v-if="hasMore" class="flex justify-center pt-4">
                        <button
                            @click="loadMore"
                            class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-6 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            Load More
                        </button>
                    </div>

                    <!-- Empty State -->
                    <div v-if="groupedDays.length === 0" class="rounded-lg bg-white dark:bg-gray-800 p-8 text-center shadow-sm dark:shadow-gray-900/20">
                        <p class="text-gray-500 dark:text-gray-400">No time entries yet.</p>
                        <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">Start the timer or add a manual entry.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
