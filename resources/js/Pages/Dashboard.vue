<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { VueDatePicker } from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';

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
const showTagInput = ref(false);
const timerTagInputRef = ref(null);

const onTagInputBlur = () => {
    if (!timerTagInput.value) showTagInput.value = false;
};

const addTimerTag = () => {
    const tag = timerTagInput.value.trim();
    if (tag && !timerTags.value.includes(tag)) {
        timerTags.value.push(tag);
    }
    timerTagInput.value = '';
    showTagInput.value = false;
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
    started_at: null,
    ended_at: null,
    tag_names: [],
});

const manualTags = ref([]);
const manualTagInput = ref('');
const showManualTagInput = ref(false);
const manualTagInputRef = ref(null);

const onManualTagInputBlur = () => {
    if (!manualTagInput.value) showManualTagInput.value = false;
};

const addManualTag = () => {
    const tag = manualTagInput.value.trim();
    if (tag && !manualTags.value.includes(tag)) {
        manualTags.value.push(tag);
    }
    manualTagInput.value = '';
    showManualTagInput.value = false;
};

const removeManualTag = (tag) => {
    manualTags.value = manualTags.value.filter(t => t !== tag);
};

const submitManualEntry = () => {
    manualForm.tag_names = manualTags.value;
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
    started_at: null,
    ended_at: null,
    tag_names: [],
});

const editTags = ref([]);
const editTagInput = ref('');
const showEditTagInput = ref(false);
const editTagInputRef = ref(null);

const onEditTagInputBlur = () => {
    if (!editTagInput.value) showEditTagInput.value = false;
};

const openEdit = (entry) => {
    editEntry.value = entry;
    editForm.description = entry.description || '';
    editForm.project_id = entry.project_id;
    editForm.started_at = entry.started_at ? new Date(entry.started_at) : null;
    editForm.ended_at = entry.ended_at ? new Date(entry.ended_at) : null;
    editTags.value = (entry.tags || []).map(t => t.name);
    editTagInput.value = '';
};

const addEditTag = () => {
    const tag = editTagInput.value.trim();
    if (tag && !editTags.value.includes(tag)) {
        editTags.value.push(tag);
    }
    editTagInput.value = '';
    showEditTagInput.value = false;
};

const removeEditTag = (tag) => {
    editTags.value = editTags.value.filter(t => t !== tag);
};

const submitEdit = () => {
    editForm.tag_names = editTags.value;
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

const getProjectGradient = (project) => {
    const color = getProjectColor(project);
    return `linear-gradient(180deg, ${color} 0%, ${color}00 100%)`;
};

// Date picker config
const datePickerConfig = {
    locale: 'en',
    format: 'yyyy-MM-dd HH:mm',
    enableTime: true,
    time24hr: true,
    position: 'left',
};
</script>

<template>
    <Head title="Time Tracker" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Time Tracker
                </h2>
                <button
                    @click="showManualEntry = !showManualEntry"
                    class="text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
                >
                    {{ showManualEntry ? 'Cancel' : '+ Manual entry' }}
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <!-- Timer Bar - Floating Card Style -->
                <div class="mb-8 rounded-2xl bg-white dark:bg-gray-800 p-2 shadow-lg shadow-gray-200/50 dark:shadow-black/20">
                    <div class="flex items-center gap-3 p-2">
                        <!-- Project Selector - Pill Style -->
                        <div class="relative shrink-0">
                            <select
                                v-model="timerForm.project_id"
                                class="appearance-none rounded-xl border-0 bg-gray-100 dark:bg-gray-700 px-4 py-3 pr-10 text-sm font-medium text-gray-700 dark:text-gray-300 focus:bg-gray-200 dark:focus:bg-gray-600 focus:ring-0 transition-colors cursor-pointer"
                            >
                                <option :value="null">No Project</option>
                                <option v-for="project in projects" :key="project.id" :value="project.id">
                                    {{ project.name }}
                                </option>
                            </select>
                            <div class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>

                        <!-- Description Input -->
                        <div class="flex-1 min-w-0">
                            <input
                                v-model="timerForm.description"
                                type="text"
                                placeholder="What are you working on?"
                                class="w-full border-0 bg-transparent px-2 py-3 text-base text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-0"
                                @keyup.enter="startTimer"
                            />
                        </div>

                        <!-- Timer Display / Action -->
                        <div class="flex items-center gap-3 shrink-0">
                            <span v-if="runningTimer" class="font-mono text-xl font-semibold text-indigo-600 dark:text-indigo-400">
                                {{ timerDisplay }}
                            </span>
                            
                            <button
                                v-if="!runningTimer"
                                @click="startTimer"
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-lg shadow-indigo-200 dark:shadow-indigo-900/30 hover:bg-indigo-700 hover:scale-105 active:scale-95 transition-all"
                            >
                                <svg class="h-5 w-5 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z" />
                                </svg>
                            </button>
                            <button
                                v-else
                                @click="stopTimer(runningTimer)"
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-500 text-white shadow-lg shadow-red-200 dark:shadow-red-900/30 hover:bg-red-600 hover:scale-105 active:scale-95 transition-all"
                            >
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M6 6h12v12H6z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Tags Row -->
                    <div class="flex items-center gap-2 px-2 pb-2">
                        <button
                            v-for="tag in timerTags"
                            :key="tag"
                            @click="removeTimerTag(tag)"
                            class="group inline-flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-gray-700 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600 dark:hover:text-red-400 transition-colors"
                        >
                            {{ tag }}
                            <svg class="h-3 w-3 opacity-50 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        
                        <button
                            v-if="!showTagInput"
                            @click="showTagInput = true"
                            class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 transition-colors"
                        >
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add tag
                        </button>
                        
                        <input
                            v-else
                            v-model="timerTagInput"
                            ref="timerTagInputRef"
                            type="text"
                            placeholder="Tag name..."
                            class="w-32 rounded-lg border-0 bg-gray-100 dark:bg-gray-700 px-3 py-1.5 text-xs focus:ring-2 focus:ring-indigo-500"
                            @keyup.enter.prevent="addTimerTag"
                            @blur="onTagInputBlur"
                        />
                    </div>
                </div>

                <!-- Manual Entry Form - Slide Down -->
                <transition
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div v-if="showManualEntry" class="mb-8 rounded-2xl bg-white dark:bg-gray-800 p-6 shadow-lg shadow-gray-200/50 dark:shadow-black/20">
                        <h3 class="mb-4 text-sm font-medium text-gray-900 dark:text-gray-100">Add Time Entry</h3>
                        
                        <div class="space-y-4">
                            <!-- Top Row: Project & Description -->
                            <div class="flex gap-3">
                                <div class="relative shrink-0">
                                    <select
                                        v-model="manualForm.project_id"
                                        class="appearance-none rounded-xl border-0 bg-gray-100 dark:bg-gray-700 px-4 py-2.5 pr-10 text-sm font-medium text-gray-700 dark:text-gray-300 focus:bg-gray-200 dark:focus:bg-gray-600 focus:ring-0 transition-colors cursor-pointer"
                                    >
                                        <option :value="null">No Project</option>
                                        <option v-for="project in projects" :key="project.id" :value="project.id">
                                            {{ project.name }}
                                        </option>
                                    </select>
                                    <div class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>
                                <input
                                    v-model="manualForm.description"
                                    type="text"
                                    placeholder="What did you work on?"
                                    class="flex-1 rounded-xl border-0 bg-gray-100 dark:bg-gray-700 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:bg-gray-200 dark:focus:bg-gray-600 focus:ring-0 transition-colors"
                                />
                            </div>

                            <!-- Time Range -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">Start Time</label>
                                    <VueDatePicker
                                        v-model="manualForm.started_at"
                                        :enable-time-picker="true"
                                        :is-24="true"
                                        format="yyyy-MM-dd HH:mm"
                                        placeholder="Select start time"
                                        class="dp-custom"
                                        auto-apply
                                    />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">End Time</label>
                                    <VueDatePicker
                                        v-model="manualForm.ended_at"
                                        :enable-time-picker="true"
                                        :is-24="true"
                                        format="yyyy-MM-dd HH:mm"
                                        placeholder="Select end time"
                                        class="dp-custom"
                                        auto-apply
                                    />
                                </div>
                            </div>

                            <!-- Tags -->
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">Tags</label>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button
                                        v-for="tag in manualTags"
                                        :key="tag"
                                        @click="removeManualTag(tag)"
                                        class="group inline-flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-gray-700 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600 dark:hover:text-red-400 transition-colors"
                                    >
                                        {{ tag }}
                                        <svg class="h-3 w-3 opacity-50 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                    
                                    <button
                                        v-if="!showManualTagInput"
                                        @click="showManualTagInput = true"
                                        class="inline-flex items-center gap-1 rounded-lg border border-dashed border-gray-300 dark:border-gray-600 px-3 py-1.5 text-xs font-medium text-gray-400 hover:text-gray-600 hover:border-gray-400 dark:text-gray-500 dark:hover:text-gray-300 transition-colors"
                                    >
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        Add tag
                                    </button>
                                    
                                    <input
                            v-else
                            v-model="manualTagInput"
                            ref="manualTagInputRef"
                            type="text"
                            placeholder="Tag name..."
                            class="w-32 rounded-lg border-0 bg-gray-100 dark:bg-gray-700 px-3 py-1.5 text-xs focus:ring-2 focus:ring-indigo-500"
                            @keyup.enter.prevent="addManualTag"
                            @blur="onManualTagInputBlur"
                        />
                                </div>
                            </div>

                            <!-- Submit -->
                            <div class="flex justify-end pt-2">
                                <button
                                    @click="submitManualEntry"
                                    :disabled="manualForm.processing"
                                    class="rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                                >
                                    Add Entry
                                </button>
                            </div>
                        </div>
                    </div>
                </transition>

                <!-- Edit Modal -->
                <div v-if="editEntry" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
                    <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-gray-800 p-6 shadow-2xl">
                        <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">Edit Time Entry</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">Description</label>
                                <input
                                    v-model="editForm.description"
                                    type="text"
                                    class="w-full rounded-xl border-0 bg-gray-100 dark:bg-gray-700 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 focus:bg-gray-200 dark:focus:bg-gray-600 focus:ring-0 transition-colors"
                                />
                            </div>
                            
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">Project</label>
                                <select v-model="editForm.project_id" class="w-full rounded-xl border-0 bg-gray-100 dark:bg-gray-700 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 focus:bg-gray-200 dark:focus:bg-gray-600 focus:ring-0 transition-colors appearance-none">
                                    <option :value="null">No Project</option>
                                    <option v-for="project in projects" :key="project.id" :value="project.id">
                                        {{ project.name }}
                                    </option>
                                </select>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">Start Time</label>
                                    <VueDatePicker
                                        v-model="editForm.started_at"
                                        :enable-time-picker="true"
                                        :is-24="true"
                                        format="yyyy-MM-dd HH:mm"
                                        class="dp-custom"
                                        auto-apply
                                    />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">End Time</label>
                                    <VueDatePicker
                                        v-model="editForm.ended_at"
                                        :enable-time-picker="true"
                                        :is-24="true"
                                        format="yyyy-MM-dd HH:mm"
                                        class="dp-custom"
                                        auto-apply
                                    />
                                </div>
                            </div>
                            
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">Tags</label>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button
                                        v-for="tag in editTags"
                                        :key="tag"
                                        @click="removeEditTag(tag)"
                                        class="group inline-flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-gray-700 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600 dark:hover:text-red-400 transition-colors"
                                    >
                                        {{ tag }}
                                        <svg class="h-3 w-3 opacity-50 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                    
                                    <button
                                        v-if="!showEditTagInput"
                                        @click="showEditTagInput = true"
                                        class="inline-flex items-center gap-1 rounded-lg border border-dashed border-gray-300 dark:border-gray-600 px-3 py-1.5 text-xs font-medium text-gray-400 hover:text-gray-600 hover:border-gray-400 dark:text-gray-500 dark:hover:text-gray-300 transition-colors"
                                    >
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        Add tag
                                    </button>
                                    
                                    <input
                                        v-else
                                        v-model="editTagInput"
                                        ref="editTagInputRef"
                                        type="text"
                                        placeholder="Tag name..."
                                        class="w-32 rounded-lg border-0 bg-gray-100 dark:bg-gray-700 px-3 py-1.5 text-xs focus:ring-2 focus:ring-indigo-500"
                                        @keyup.enter.prevent="addEditTag"
                                        @blur="onEditTagInputBlur"
                                    />
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end gap-3">
                            <button
                                @click="editEntry = null"
                                class="rounded-xl px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                @click="submitEdit"
                                :disabled="editForm.processing"
                                class="rounded-xl bg-indigo-600 px-6 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                            >
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Entries Grouped by Day -->
                <div class="space-y-6">
                    <template v-for="(day, dayIndex) in groupedDays" :key="day.date">
                        <!-- Week Header -->
                        <div v-if="day.show_week_header" class="flex items-center gap-4 py-3">
                            <div class="h-px flex-1 bg-gradient-to-r from-transparent via-gray-200 dark:via-gray-700 to-transparent"></div>
                            <span class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider">
                                {{ day.week_start }} — {{ day.week_end }}
                            </span>
                            <div class="h-px flex-1 bg-gradient-to-r from-transparent via-gray-200 dark:via-gray-700 to-transparent"></div>
                        </div>

                        <!-- Day Header - Sticky Style -->
                        <div class="sticky top-0 z-10 flex items-center justify-between py-3 bg-gray-50/80 dark:bg-gray-900/80 backdrop-blur-sm">
                            <div class="flex items-center gap-3">
                                <div 
                                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-white dark:bg-gray-800 shadow-sm text-sm font-semibold"
                                    :class="day.is_today ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400'"
                                >
                                    {{ new Date(day.date).getDate() }}
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        <span v-if="day.is_today" class="text-indigo-600 dark:text-indigo-400">Today</span>
                                        <span v-else-if="day.is_yesterday">Yesterday</span>
                                        <span v-else>{{ day.day_label }}</span>
                                    </h3>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ new Date(day.date).toLocaleDateString('en-US', { month: 'short', year: 'numeric' }) }}</p>
                                </div>
                            </div>
                            <span class="font-mono text-sm font-medium text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 px-3 py-1 rounded-lg shadow-sm">
                                {{ formatDurationShort(day.total_seconds) }}
                            </span>
                        </div>

                        <!-- Day Entries -->
                        <div class="space-y-2">
                            <div
                                v-for="entry in day.entries"
                                :key="entry.id"
                                class="group relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 shadow-sm hover:shadow-md transition-all duration-200"
                            >
                                <!-- Left Edge Gradient -->
                                <div
                                    class="absolute left-0 top-0 bottom-0 w-1"
                                    :style="{ background: getProjectGradient(entry.project) }"
                                ></div>

                                <div class="flex items-center justify-between p-4 pl-5">
                                    <!-- Left: Description & Meta -->
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                            {{ entry.description || 'No description' }}
                                        </p>
                                        <div class="flex items-center gap-3 mt-1">
                                            <span 
                                                class="text-xs font-medium"
                                                :style="{ color: getProjectColor(entry.project) }"
                                            >
                                                {{ getProjectName(entry.project) }}
                                            </span>
                                            <template v-if="entry.tags && entry.tags.length">
                                                <span class="text-gray-300 dark:text-gray-600">·</span>
                                                <div class="flex items-center gap-1.5">
                                                    <span
                                                        v-for="tag in entry.tags"
                                                        :key="tag.id"
                                                        class="text-xs text-gray-400 dark:text-gray-500"
                                                    >
                                                        #{{ tag.name }}
                                                    </span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Right: Time & Actions -->
                                    <div class="flex items-center gap-4 ml-4">
                                        <div class="text-right">
                                            <p class="font-mono text-sm font-semibold text-gray-700 dark:text-gray-300">
                                                {{ entry.formatted_duration || formatDuration(entry.duration_seconds) }}
                                            </p>
                                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                                {{ formatTime(entry.started_at) }} – {{ formatTime(entry.ended_at) }}
                                            </p>
                                        </div>
                                        
                                        <!-- Action Buttons - Always visible on mobile, hover on desktop -->
                                        <div class="flex items-center gap-1 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                                            <button
                                                v-if="entry.ended_at"
                                                @click="restartTimer(entry)"
                                                title="Restart"
                                                class="p-2 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                            </button>
                                            <button
                                                v-if="entry.ended_at"
                                                @click="openEdit(entry)"
                                                class="p-2 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                v-if="entry.ended_at === null"
                                                @click="stopTimer(entry)"
                                                class="p-2 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                                                </svg>
                                            </button>
                                            <button
                                                @click="deleteEntry(entry)"
                                                class="p-2 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
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
                    <div v-if="hasMore" class="flex justify-center pt-6">
                        <button
                            @click="loadMore"
                            class="group flex items-center gap-2 rounded-xl bg-white dark:bg-gray-800 px-6 py-3 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 shadow-sm hover:shadow-md transition-all"
                        >
                            Load More
                            <svg class="h-4 w-4 transition-transform group-hover:translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>

                    <!-- Empty State -->
                    <div v-if="groupedDays.length === 0" class="rounded-2xl bg-white dark:bg-gray-800 p-12 text-center shadow-sm">
                        <div class="mx-auto h-12 w-12 rounded-2xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center mb-4">
                            <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="text-gray-900 dark:text-gray-100 font-medium">No time entries yet</p>
                        <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">Start the timer or add a manual entry</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
/* Custom Date Picker Styles */
.dp-custom .dp__input {
    border: none;
    border-radius: 0.75rem;
    background-color: rgb(243 244 246);
    padding: 0.625rem 1rem;
    font-size: 0.875rem;
    color: rgb(17 24 39);
    transition: all 0.2s;
}

.dp-custom .dp__input:hover {
    background-color: rgb(229 231 235);
}

.dp-custom .dp__input:focus {
    background-color: rgb(229 231 235);
    box-shadow: none;
}

.dark .dp-custom .dp__input {
    background-color: rgb(55 65 81);
    color: rgb(243 244 246);
}

.dark .dp-custom .dp__input:hover {
    background-color: rgb(75 85 99);
}

.dark .dp-custom .dp__input:focus {
    background-color: rgb(75 85 99);
}

.dp-custom .dp__input_icon {
    color: rgb(156 163 175);
}

.dp-custom .dp__clear_icon {
    color: rgb(156 163 175);
}

.dp-custom .dp__menu {
    border-radius: 0.75rem;
    box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.2);
}
</style>
