<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import {
    Play,
    Square,
    Plus,
    X,
    Pencil,
    Trash2,
    RotateCcw,
    Tag as TagIcon,
    Folder,
    Hourglass,
    CalendarDays,
    Clock,
    Sparkles,
} from 'lucide-vue-next';
import ProjectSelect from '@/Components/ProjectSelect.vue';

const pad = (n) => String(n).padStart(2, '0');

const toDateInput = (d) => {
    if (!d) return '';
    const dt = d instanceof Date ? d : new Date(d);
    if (Number.isNaN(dt.getTime())) return '';
    return `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())}`;
};

const toTimeInput = (d) => {
    if (!d) return '';
    const dt = d instanceof Date ? d : new Date(d);
    if (Number.isNaN(dt.getTime())) return '';
    return `${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
};

const combineDateTime = (date, time) => {
    if (!date) return null;
    const t = time && time.length >= 4 ? time : '00:00';
    const dt = new Date(`${date}T${t}:00`);
    if (Number.isNaN(dt.getTime())) return null;
    return dt.toISOString();
};

const nowDateInput = () => toDateInput(new Date());
const nowTimeInput = () => toTimeInput(new Date());

const props = defineProps({
    groupedDays: Array,
    hasMore: Boolean,
    nextCursor: String,
    totalSeconds: Number,
    todayTotalSeconds: Number,
    weekTotalSeconds: Number,
    projects: Array,
    tags: Array,
});

const runningTimer = computed(() => {
    for (const day of props.groupedDays) {
        const t = day.entries.find((e) => !e.ended_at);
        if (t) return t;
    }
    return null;
});

const liveElapsed = ref(0);
let timerInterval = null;

const formatHMS = (seconds) => {
    const s = Math.max(0, Math.floor(seconds));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
};

const formatHM = (seconds) => {
    const s = Math.max(0, Math.floor(seconds));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
};

const formatTime = (dateStr) => {
    if (!dateStr) return '--:--';
    return new Date(dateStr).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
};

const startTimerInterval = () => {
    if (timerInterval) clearInterval(timerInterval);
    if (!runningTimer.value) {
        liveElapsed.value = 0;
        return;
    }
    const startedAt = new Date(runningTimer.value.started_at).getTime();
    const tick = () => {
        liveElapsed.value = Math.floor((Date.now() - startedAt) / 1000);
    };
    tick();
    timerInterval = setInterval(tick, 1000);
};

onMounted(() => {
    startTimerInterval();
    initTimerForm();
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});

watch(runningTimer, (newTimer, oldTimer) => {
    const wasRunning = !!oldTimer;
    const isRunning = !!newTimer;

    if (!wasRunning && isRunning) {
        startTimerInterval();
        initTimerForm();
    } else if (wasRunning && !isRunning) {
        startTimerInterval();
        initTimerForm();
    } else if (newTimer && oldTimer && newTimer.id !== oldTimer.id) {
        startTimerInterval();
        initTimerForm();
    }
});

const liveTimerDisplay = computed(() => formatHMS(liveElapsed.value));

const todayDisplay = computed(() =>
    formatHM((props.todayTotalSeconds || 0) + (runningTimer.value ? liveElapsed.value : 0))
);
const weekDisplay = computed(() =>
    formatHM((props.weekTotalSeconds || 0) + (runningTimer.value ? liveElapsed.value : 0))
);

const timerForm = useForm({
    description: '',
    project_id: null,
    tag_names: [],
});

const timerTags = ref([]);
const timerTagInput = ref('');
const showTagInput = ref(false);
const timerTagInputRef = ref(null);

const initTimerForm = () => {
    if (runningTimer.value) {
        timerForm.description = runningTimer.value.description || '';
        timerForm.project_id = runningTimer.value.project_id;
        timerTags.value = (runningTimer.value.tags || []).map((t) => t.name);
    } else {
        timerForm.description = '';
        timerForm.project_id = null;
        timerTags.value = [];
    }
};

const onTagInputBlur = () => {
    if (!timerTagInput.value) showTagInput.value = false;
};

const focusTagInput = async () => {
    showTagInput.value = true;
    await nextTick();
    timerTagInputRef.value?.focus();
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
    timerTags.value = timerTags.value.filter((t) => t !== tag);
};

const startTimer = () => {
    if (runningTimer.value) return;
    timerForm.tag_names = timerTags.value;
    timerForm.post(route('time-entries.store'), {
        preserveScroll: true,
        onSuccess: () => {
            timerTagInput.value = '';
            router.reload({ only: ['groupedDays', 'hasMore', 'nextCursor', 'totalSeconds', 'todayTotalSeconds', 'weekTotalSeconds'] });
        },
    });
};

const stopTimer = (entry) => {
    const target = entry || runningTimer.value;
    if (!target) return;
    router.post(
        route('time-entries.stop', target.id),
        {
            description: timerForm.description,
            project_id: timerForm.project_id,
            tag_names: timerTags.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['groupedDays', 'hasMore', 'nextCursor', 'totalSeconds', 'todayTotalSeconds', 'weekTotalSeconds'] });
            },
        }
    );
};

const restartTimer = (entry) => {
    router.post(
        route('time-entries.restart', entry.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['groupedDays', 'hasMore', 'nextCursor', 'totalSeconds', 'todayTotalSeconds', 'weekTotalSeconds'] }),
        }
    );
};

const deleteEntry = (entry) => {
    if (confirm('Delete this time entry?')) {
        router.delete(route('time-entries.destroy', entry.id), {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['groupedDays', 'hasMore', 'nextCursor', 'totalSeconds', 'todayTotalSeconds', 'weekTotalSeconds'] }),
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

const manualStartDate = ref('');
const manualStartTime = ref('');
const manualEndDate = ref('');
const manualEndTime = ref('');

const manualDuration = computed(() => {
    const s = combineDateTime(manualStartDate.value, manualStartTime.value);
    const e = combineDateTime(manualEndDate.value, manualEndTime.value);
    if (!s || !e) return null;
    const diff = (new Date(e.replace(' ', 'T')) - new Date(s.replace(' ', 'T'))) / 1000;
    if (Number.isNaN(diff) || diff <= 0) return null;
    return formatHM(diff);
});

watch(showManualEntry, (open) => {
    if (open && !manualStartDate.value) {
        manualStartDate.value = nowDateInput();
        manualEndDate.value = nowDateInput();
    }
});

const manualTags = ref([]);
const manualTagInput = ref('');
const showManualTagInput = ref(false);
const manualTagInputRef = ref(null);

const onManualTagInputBlur = () => {
    if (!manualTagInput.value) showManualTagInput.value = false;
};

const focusManualTagInput = async () => {
    showManualTagInput.value = true;
    await nextTick();
    manualTagInputRef.value?.focus();
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
    manualTags.value = manualTags.value.filter((t) => t !== tag);
};

const submitManualEntry = () => {
    manualForm.started_at = combineDateTime(manualStartDate.value, manualStartTime.value);
    manualForm.ended_at = combineDateTime(manualEndDate.value, manualEndTime.value);
    manualForm.tag_names = manualTags.value;
    manualForm.post(route('time-entries.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showManualEntry.value = false;
            manualForm.reset();
            manualTags.value = [];
            manualStartDate.value = '';
            manualStartTime.value = '';
            manualEndDate.value = '';
            manualEndTime.value = '';
            router.reload({ only: ['groupedDays', 'hasMore', 'nextCursor', 'totalSeconds', 'todayTotalSeconds', 'weekTotalSeconds'] });
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

const editStartDate = ref('');
const editStartTime = ref('');
const editEndDate = ref('');
const editEndTime = ref('');

const editDuration = computed(() => {
    const s = combineDateTime(editStartDate.value, editStartTime.value);
    const e = combineDateTime(editEndDate.value, editEndTime.value);
    if (!s || !e) return null;
    const diff = (new Date(e.replace(' ', 'T')) - new Date(s.replace(' ', 'T'))) / 1000;
    if (Number.isNaN(diff) || diff <= 0) return null;
    return formatHM(diff);
});

const editTags = ref([]);
const editTagInput = ref('');
const showEditTagInput = ref(false);
const editTagInputRef = ref(null);

const onEditTagInputBlur = () => {
    if (!editTagInput.value) showEditTagInput.value = false;
};

const focusEditTagInput = async () => {
    showEditTagInput.value = true;
    await nextTick();
    editTagInputRef.value?.focus();
};

const openEdit = (entry) => {
    editEntry.value = entry;
    editForm.description = entry.description || '';
    editForm.project_id = entry.project_id;
    editStartDate.value = toDateInput(entry.started_at);
    editStartTime.value = toTimeInput(entry.started_at);
    editEndDate.value = toDateInput(entry.ended_at);
    editEndTime.value = toTimeInput(entry.ended_at);
    editTags.value = (entry.tags || []).map((t) => t.name);
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
    editTags.value = editTags.value.filter((t) => t !== tag);
};

const submitEdit = () => {
    editForm.started_at = combineDateTime(editStartDate.value, editStartTime.value);
    editForm.ended_at = combineDateTime(editEndDate.value, editEndTime.value);
    editForm.tag_names = editTags.value;
    editForm.put(route('time-entries.update', editEntry.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editEntry.value = null;
            router.reload({ only: ['groupedDays', 'hasMore', 'nextCursor', 'totalSeconds', 'todayTotalSeconds', 'weekTotalSeconds'] });
        },
    });
};

const loadMore = () => {
    if (!props.hasMore || !props.nextCursor) return;
    router.get(
        route('dashboard'),
        { cursor: props.nextCursor },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['groupedDays', 'hasMore', 'nextCursor', 'totalSeconds'],
        }
    );
};

const getProjectColor = (project) => project?.color || '#94a3b8';
const getProjectName = (project) => project?.name || 'No project';

const projectById = computed(() => {
    const map = new Map();
    (props.projects || []).forEach((p) => {
        map.set(p.id, p);
        if (p.children) {
            p.children.forEach((c) => map.set(c.id, c));
        }
    });
    return map;
});

const selectedTimerProject = computed(() => projectById.value.get(timerForm.project_id) || null);
</script>

<template>
    <Head title="Tempo · Time Tracker" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-white">
                        Time Tracker
                    </h2>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        Track what you work on and where your hours go.
                    </p>
                </div>
                <button
                    @click="showManualEntry = !showManualEntry"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                >
                    <Plus v-if="!showManualEntry" class="h-4 w-4" />
                    <X v-else class="h-4 w-4" />
                    {{ showManualEntry ? 'Cancel' : 'Manual entry' }}
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Stat Cards -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="group relative overflow-hidden rounded-2xl border border-slate-200/70 bg-white p-4 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400">
                                <Clock class="h-5 w-5" />
                            </div>
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Today</p>
                                <p class="font-mono text-xl font-semibold tabular-nums text-slate-900 dark:text-white">
                                    {{ todayDisplay }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="group relative overflow-hidden rounded-2xl border border-slate-200/70 bg-white p-4 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-fuchsia-100 text-fuchsia-600 dark:bg-fuchsia-500/15 dark:text-fuchsia-400">
                                <CalendarDays class="h-5 w-5" />
                            </div>
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">This week (Sat–Fri)</p>
                                <p class="font-mono text-xl font-semibold tabular-nums text-slate-900 dark:text-white">
                                    {{ weekDisplay }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="group relative overflow-hidden rounded-2xl border border-slate-200/70 bg-white p-4 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                                <Hourglass class="h-5 w-5" />
                            </div>
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    {{ runningTimer ? 'Running' : 'Idle' }}
                                </p>
                                <p class="font-mono text-xl font-semibold tabular-nums text-slate-900 dark:text-white">
                                    {{ runningTimer ? liveTimerDisplay : '00:00:00' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timer Card -->
                <div
                    class="relative rounded-2xl border bg-white shadow-lg transition-colors dark:bg-slate-900"
                    :class="runningTimer
                        ? 'border-indigo-300/60 shadow-indigo-200/40 dark:border-indigo-500/40 dark:shadow-indigo-500/10'
                        : 'border-slate-200/70 dark:border-slate-800'"
                >
                    <div
                        v-if="runningTimer"
                        class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-indigo-500 to-transparent"
                    />
                    <div
                        v-if="runningTimer"
                        class="pointer-events-none absolute -top-24 -right-24 h-48 w-48 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-3xl"
                    />

                    <div class="relative flex flex-wrap items-center gap-3 p-3 sm:flex-nowrap">
                        <!-- Project pill -->
                        <ProjectSelect
                            v-model="timerForm.project_id"
                            :projects="projects"
                            placeholder="No project"
                            class="w-48 shrink-0"
                        />

                        <!-- Description -->
                        <div class="flex min-w-0 flex-1 items-center">
                            <input
                                v-model="timerForm.description"
                                type="text"
                                :placeholder="runningTimer ? 'Add what you\'re working on…' : 'What are you working on?'"
                                class="w-full border-0 bg-transparent px-3 py-3 text-base text-slate-900 placeholder:text-slate-400 focus:ring-0 dark:text-white dark:placeholder:text-slate-500"
                                @keyup.enter="runningTimer ? stopTimer() : startTimer()"
                            />
                        </div>

                        <!-- Live timer + action -->
                        <div class="flex shrink-0 items-center gap-3">
                            <div v-if="runningTimer" class="flex items-center gap-2 font-mono text-xl font-semibold tabular-nums text-slate-900 dark:text-white">
                                <span class="relative flex h-2 w-2">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-indigo-400 opacity-75"></span>
                                    <span class="relative inline-flex h-2 w-2 rounded-full bg-indigo-500"></span>
                                </span>
                                {{ liveTimerDisplay }}
                            </div>

                            <button
                                v-if="!runningTimer"
                                @click="startTimer"
                                :disabled="timerForm.processing"
                                class="group flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-fuchsia-500 text-white shadow-lg shadow-indigo-500/30 transition hover:scale-105 hover:shadow-xl hover:shadow-indigo-500/40 active:scale-95 disabled:opacity-50"
                                title="Start timer"
                            >
                                <Play class="h-5 w-5 translate-x-0.5" :stroke-width="2.5" />
                            </button>
                            <button
                                v-else
                                @click="stopTimer()"
                                class="group flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-500 text-white shadow-lg shadow-rose-500/30 transition hover:scale-105 hover:bg-rose-600 active:scale-95"
                                title="Stop timer"
                            >
                                <Square class="h-4 w-4" fill="currentColor" :stroke-width="0" />
                            </button>
                        </div>
                    </div>

                    <!-- Tags row -->
                    <div class="relative flex flex-wrap items-center gap-1.5 px-3 pb-3">
                        <button
                            v-for="tag in timerTags"
                            :key="tag"
                            @click="removeTimerTag(tag)"
                            class="group inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 transition hover:bg-rose-50 hover:text-rose-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-rose-500/15 dark:hover:text-rose-400"
                        >
                            <TagIcon class="h-3 w-3 opacity-60" />
                            {{ tag }}
                            <X class="h-3 w-3 opacity-50 group-hover:opacity-100" />
                        </button>

                        <button
                            v-if="!showTagInput"
                            @click="focusTagInput"
                            class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-slate-400 transition hover:text-slate-700 dark:text-slate-500 dark:hover:text-slate-300"
                        >
                            <Plus class="h-3 w-3" />
                            Add tag
                        </button>

                        <input
                            v-else
                            v-model="timerTagInput"
                            ref="timerTagInputRef"
                            type="text"
                            placeholder="Tag name…"
                            class="w-32 rounded-md border-0 bg-slate-100 px-2 py-1 text-xs focus:ring-2 focus:ring-indigo-500/40 dark:bg-slate-800 dark:text-slate-200"
                            @keyup.enter.prevent="addTimerTag"
                            @keyup.escape="showTagInput = false; timerTagInput = ''"
                            @blur="onTagInputBlur"
                        />
                    </div>
                </div>

                <!-- Manual entry form -->
                <transition
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div
                        v-if="showManualEntry"
                        class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div class="mb-4 flex items-center gap-2">
                            <Sparkles class="h-4 w-4 text-indigo-500" />
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Add past entry</h3>
                        </div>

                        <div class="space-y-4">
                            <div class="flex flex-col gap-3 sm:flex-row">
                                <ProjectSelect
                                    v-model="manualForm.project_id"
                                    :projects="projects"
                                    placeholder="No project"
                                    class="w-full shrink-0 sm:w-48"
                                />
                                <input
                                    v-model="manualForm.description"
                                    type="text"
                                    placeholder="What did you work on?"
                                    class="flex-1 rounded-xl border-0 bg-slate-100 px-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:bg-slate-200 focus:ring-2 focus:ring-indigo-500/30 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500 dark:hover:bg-slate-700"
                                />
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <div class="mb-1.5 flex items-center justify-between">
                                        <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Start</label>
                                        <button
                                            type="button"
                                            @click="manualStartDate = nowDateInput(); manualStartTime = nowTimeInput()"
                                            class="text-[11px] font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                                        >
                                            Now
                                        </button>
                                    </div>
                                    <div class="flex gap-2">
                                        <input
                                            v-model="manualStartDate"
                                            type="date"
                                            class="dt-input flex-1"
                                        />
                                        <input
                                            v-model="manualStartTime"
                                            type="time"
                                            class="dt-input w-28"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <div class="mb-1.5 flex items-center justify-between">
                                        <label class="text-xs font-medium text-slate-500 dark:text-slate-400">End</label>
                                        <button
                                            type="button"
                                            @click="manualEndDate = nowDateInput(); manualEndTime = nowTimeInput()"
                                            class="text-[11px] font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                                        >
                                            Now
                                        </button>
                                    </div>
                                    <div class="flex gap-2">
                                        <input
                                            v-model="manualEndDate"
                                            type="date"
                                            class="dt-input flex-1"
                                        />
                                        <input
                                            v-model="manualEndTime"
                                            type="time"
                                            class="dt-input w-28"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div v-if="manualDuration" class="-mt-1 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                <Clock class="h-3.5 w-3.5" />
                                Duration: <span class="font-mono font-semibold text-slate-700 dark:text-slate-200">{{ manualDuration }}</span>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-slate-500 dark:text-slate-400">Tags</label>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <button
                                        v-for="tag in manualTags"
                                        :key="tag"
                                        @click="removeManualTag(tag)"
                                        class="group inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-rose-50 hover:text-rose-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-rose-500/15 dark:hover:text-rose-400"
                                    >
                                        <TagIcon class="h-3 w-3 opacity-60" />
                                        {{ tag }}
                                        <X class="h-3 w-3 opacity-50 group-hover:opacity-100" />
                                    </button>
                                    <button
                                        v-if="!showManualTagInput"
                                        @click="focusManualTagInput"
                                        class="inline-flex items-center gap-1 rounded-md border border-dashed border-slate-300 px-2 py-1 text-xs font-medium text-slate-400 hover:border-slate-400 hover:text-slate-700 dark:border-slate-700 dark:text-slate-500 dark:hover:text-slate-300"
                                    >
                                        <Plus class="h-3 w-3" />
                                        Add tag
                                    </button>
                                    <input
                                        v-else
                                        v-model="manualTagInput"
                                        ref="manualTagInputRef"
                                        type="text"
                                        placeholder="Tag name…"
                                        class="w-32 rounded-md border-0 bg-slate-100 px-2 py-1 text-xs focus:ring-2 focus:ring-indigo-500/40 dark:bg-slate-800 dark:text-slate-200"
                                        @keyup.enter.prevent="addManualTag"
                                        @keyup.escape="showManualTagInput = false; manualTagInput = ''"
                                        @blur="onManualTagInputBlur"
                                    />
                                </div>
                            </div>

                            <div class="flex justify-end pt-2">
                                <button
                                    @click="submitManualEntry"
                                    :disabled="manualForm.processing"
                                    class="rounded-xl bg-gradient-to-br from-indigo-500 to-fuchsia-500 px-5 py-2.5 text-sm font-medium text-white shadow-md shadow-indigo-500/25 transition hover:shadow-lg disabled:opacity-50"
                                >
                                    Add entry
                                </button>
                            </div>
                        </div>
                    </div>
                </transition>

                <!-- Edit modal -->
                <transition
                    enter-active-class="transition duration-150"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="transition duration-100"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="editEntry"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm"
                        @click.self="editEntry = null"
                    >
                        <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                            <div class="mb-5 flex items-center justify-between">
                                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Edit entry</h3>
                                <button @click="editEntry = null" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-slate-500 dark:text-slate-400">Description</label>
                                    <input
                                        v-model="editForm.description"
                                        type="text"
                                        class="w-full rounded-xl border-0 bg-slate-100 px-4 py-2.5 text-sm text-slate-900 focus:bg-slate-200 focus:ring-2 focus:ring-indigo-500/30 dark:bg-slate-800 dark:text-white"
                                    />
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-slate-500 dark:text-slate-400">Project</label>
                                    <ProjectSelect
                                        v-model="editForm.project_id"
                                        :projects="projects"
                                        placeholder="No project"
                                        class="w-full"
                                    />
                                </div>

                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-medium text-slate-500 dark:text-slate-400">Start</label>
                                        <div class="flex gap-2">
                                            <input
                                                v-model="editStartDate"
                                                type="date"
                                                class="dt-input flex-1"
                                            />
                                            <input
                                                v-model="editStartTime"
                                                type="time"
                                                class="dt-input w-28"
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-medium text-slate-500 dark:text-slate-400">End</label>
                                        <div class="flex gap-2">
                                            <input
                                                v-model="editEndDate"
                                                type="date"
                                                class="dt-input flex-1"
                                            />
                                            <input
                                                v-model="editEndTime"
                                                type="time"
                                                class="dt-input w-28"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div v-if="editDuration" class="-mt-1 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                    <Clock class="h-3.5 w-3.5" />
                                    Duration: <span class="font-mono font-semibold text-slate-700 dark:text-slate-200">{{ editDuration }}</span>
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-slate-500 dark:text-slate-400">Tags</label>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <button
                                            v-for="tag in editTags"
                                            :key="tag"
                                            @click="removeEditTag(tag)"
                                            class="group inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-rose-50 hover:text-rose-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-rose-500/15 dark:hover:text-rose-400"
                                        >
                                            <TagIcon class="h-3 w-3 opacity-60" />
                                            {{ tag }}
                                            <X class="h-3 w-3 opacity-50 group-hover:opacity-100" />
                                        </button>
                                        <button
                                            v-if="!showEditTagInput"
                                            @click="focusEditTagInput"
                                            class="inline-flex items-center gap-1 rounded-md border border-dashed border-slate-300 px-2 py-1 text-xs font-medium text-slate-400 hover:border-slate-400 hover:text-slate-700 dark:border-slate-700 dark:text-slate-500 dark:hover:text-slate-300"
                                        >
                                            <Plus class="h-3 w-3" />
                                            Add tag
                                        </button>
                                        <input
                                            v-else
                                            v-model="editTagInput"
                                            ref="editTagInputRef"
                                            type="text"
                                            placeholder="Tag name…"
                                            class="w-32 rounded-md border-0 bg-slate-100 px-2 py-1 text-xs focus:ring-2 focus:ring-indigo-500/40 dark:bg-slate-800 dark:text-slate-200"
                                            @keyup.enter.prevent="addEditTag"
                                            @keyup.escape="showEditTagInput = false; editTagInput = ''"
                                            @blur="onEditTagInputBlur"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3">
                                <button
                                    @click="editEntry = null"
                                    class="rounded-xl px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                                >
                                    Cancel
                                </button>
                                <button
                                    @click="submitEdit"
                                    :disabled="editForm.processing"
                                    class="rounded-xl bg-gradient-to-br from-indigo-500 to-fuchsia-500 px-5 py-2 text-sm font-medium text-white shadow-md shadow-indigo-500/25 transition hover:shadow-lg disabled:opacity-50"
                                >
                                    Save changes
                                </button>
                            </div>
                        </div>
                    </div>
                </transition>

                <!-- Entries grouped by day -->
                <div class="space-y-6">
                    <template v-for="day in groupedDays" :key="day.date">
                        <!-- Week divider -->
                        <div v-if="day.show_week_header" class="flex items-center gap-3 pt-2">
                            <div class="h-px flex-1 bg-gradient-to-r from-transparent via-slate-200 to-transparent dark:via-slate-800"></div>
                            <div class="flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-medium uppercase tracking-wider text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
                                <span>{{ day.week_start }} — {{ day.week_end }}</span>
                                <span v-if="day.week_total_seconds !== null" class="font-mono normal-case tracking-normal text-slate-700 dark:text-slate-200">
                                    · {{ formatHM(day.week_total_seconds) }}
                                </span>
                            </div>
                            <div class="h-px flex-1 bg-gradient-to-r from-transparent via-slate-200 to-transparent dark:via-slate-800"></div>
                        </div>

                        <!-- Day header -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-11 w-11 flex-col items-center justify-center rounded-xl border text-xs font-semibold leading-none"
                                    :class="day.is_today
                                        ? 'border-indigo-200 bg-indigo-50 text-indigo-600 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300'
                                        : 'border-slate-200 bg-white text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'"
                                >
                                    <span class="text-[10px] uppercase opacity-60">{{ new Date(day.date).toLocaleDateString('en-US', { month: 'short' }) }}</span>
                                    <span class="text-base">{{ new Date(day.date).getDate() }}</span>
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                                        <span v-if="day.is_today">Today</span>
                                        <span v-else-if="day.is_yesterday">Yesterday</span>
                                        <span v-else>{{ new Date(day.date).toLocaleDateString('en-US', { weekday: 'long' }) }}</span>
                                    </h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ new Date(day.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                                    </p>
                                </div>
                            </div>
                            <span class="rounded-lg bg-slate-100 px-2.5 py-1 font-mono text-xs font-semibold tabular-nums text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                {{ formatHM(day.total_seconds) }}
                            </span>
                        </div>

                        <!-- Entries -->
                        <div class="space-y-1.5">
                            <div
                                v-for="entry in day.entries"
                                :key="entry.id"
                                class="group relative overflow-hidden rounded-xl border border-slate-200/70 bg-white transition-all hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700"
                                :class="!entry.ended_at ? 'ring-1 ring-indigo-300/40 dark:ring-indigo-500/30' : ''"
                            >
                                <div
                                    class="absolute inset-y-0 left-0 w-1"
                                    :style="{ backgroundColor: getProjectColor(entry.project) }"
                                ></div>

                                <div class="flex items-center gap-3 p-3 pl-4">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-slate-900 dark:text-white">
                                            {{ entry.description || 'No description' }}
                                            <span v-if="!entry.ended_at" class="ml-1 inline-flex items-center gap-1 rounded-md bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400">
                                                <span class="relative flex h-1.5 w-1.5">
                                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-indigo-400 opacity-75"></span>
                                                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                                                </span>
                                                Live
                                            </span>
                                        </p>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                            <span
                                                class="inline-flex items-center gap-1 font-medium"
                                                :style="{ color: getProjectColor(entry.project) }"
                                            >
                                                <Folder class="h-3 w-3" />
                                                {{ getProjectName(entry.project) }}
                                            </span>
                                            <span
                                                v-for="tag in entry.tags || []"
                                                :key="tag.id"
                                                class="text-slate-400 dark:text-slate-500"
                                            >
                                                #{{ tag.name }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="ml-2 flex items-center gap-3">
                                        <div class="text-right">
                                            <p class="font-mono text-sm font-semibold tabular-nums text-slate-800 dark:text-slate-100">
                                                {{ entry.ended_at ? (entry.formatted_duration || formatHM(entry.duration_seconds)) : liveTimerDisplay }}
                                            </p>
                                            <p class="text-[11px] text-slate-400 dark:text-slate-500">
                                                {{ formatTime(entry.started_at) }} – {{ formatTime(entry.ended_at) }}
                                            </p>
                                        </div>

                                        <div class="flex items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                                            <button
                                                v-if="entry.ended_at"
                                                @click="restartTimer(entry)"
                                                title="Resume"
                                                class="rounded-md p-1.5 text-slate-400 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-500/15 dark:hover:text-indigo-400"
                                            >
                                                <RotateCcw class="h-4 w-4" />
                                            </button>
                                            <button
                                                v-if="entry.ended_at"
                                                @click="openEdit(entry)"
                                                title="Edit"
                                                class="rounded-md p-1.5 text-slate-400 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-500/15 dark:hover:text-indigo-400"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </button>
                                            <button
                                                v-if="!entry.ended_at"
                                                @click="stopTimer(entry)"
                                                title="Stop"
                                                class="rounded-md p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/15 dark:hover:text-rose-400"
                                            >
                                                <Square class="h-4 w-4" fill="currentColor" :stroke-width="0" />
                                            </button>
                                            <button
                                                @click="deleteEntry(entry)"
                                                title="Delete"
                                                class="rounded-md p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/15 dark:hover:text-rose-400"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Load more -->
                    <div v-if="hasMore" class="flex justify-center pt-4">
                        <button
                            @click="loadMore"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Load more
                        </button>
                    </div>

                    <!-- Empty -->
                    <div
                        v-if="groupedDays.length === 0"
                        class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-100 to-fuchsia-100 text-indigo-600 dark:from-indigo-500/15 dark:to-fuchsia-500/15 dark:text-indigo-300">
                            <Hourglass class="h-5 w-5" />
                        </div>
                        <p class="font-medium text-slate-900 dark:text-white">No time entries yet</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Start the timer above or add a manual entry to get going.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
.dt-input {
    border: 0;
    border-radius: 0.75rem;
    background-color: rgb(241 245 249);
    padding: 0.625rem 0.875rem;
    font-size: 0.875rem;
    font-variant-numeric: tabular-nums;
    color: rgb(15 23 42);
    transition: background-color 0.15s, box-shadow 0.15s;
    min-width: 0;
}
.dt-input:hover {
    background-color: rgb(226 232 240);
}
.dt-input:focus {
    background-color: rgb(226 232 240);
    outline: none;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.35);
}
.dt-input::-webkit-calendar-picker-indicator {
    cursor: pointer;
    opacity: 0.5;
    transition: opacity 0.15s;
}
.dt-input:hover::-webkit-calendar-picker-indicator {
    opacity: 0.85;
}
.dark .dt-input {
    background-color: rgb(30 41 59);
    color: rgb(241 245 249);
    color-scheme: dark;
}
.dark .dt-input:hover {
    background-color: rgb(51 65 85);
}
.dark .dt-input:focus {
    background-color: rgb(51 65 85);
}
</style>
