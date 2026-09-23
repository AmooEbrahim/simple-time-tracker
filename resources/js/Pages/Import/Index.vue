<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    Upload,
    Download,
    FileText,
    CircleAlert,
    CircleCheck,
    Copy,
    Check,
    Folder,
    Info,
} from 'lucide-vue-next';

const props = defineProps({
    projects: Array,
});

const page = usePage();

const form = useForm({
    file: null,
});

const fileInput = ref(null);
const selectedFileName = ref('');
const copiedId = ref(null);
const copiedSample = ref(false);

const flashSuccess = computed(() => page.props.flash?.success);
const importErrors = computed(() => page.props.flash?.importErrors || []);
const importErrorCount = computed(() => page.props.flash?.importErrorCount || 0);

const sampleCsv = `description,project_id,date,start_time,end_time,tags
Design homepage,1,2026-09-01,09:00,11:30,design;urgent
Fix login bug,2,2026-09-01,13:00,14:15,
Team meeting,1,2026-09-02,10:00,10:30,meeting`;

const onFileChange = (e) => {
    const f = e.target.files[0] || null;
    form.file = f;
    selectedFileName.value = f ? f.name : '';
};

const submit = () => {
    form.post(route('import.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            selectedFileName.value = '';
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};

const downloadSample = () => {
    window.open(route('import.sample'), '_blank');
};

const copySample = async () => {
    try {
        await navigator.clipboard.writeText(sampleCsv);
        copiedSample.value = true;
        setTimeout(() => (copiedSample.value = false), 1500);
    } catch {
        // clipboard unavailable — user can download instead
    }
};

const copyId = async (id) => {
    try {
        await navigator.clipboard.writeText(String(id));
        copiedId.value = id;
        setTimeout(() => {
            if (copiedId.value === id) copiedId.value = null;
        }, 1200);
    } catch {
        // ignore
    }
};

const columns = [
    { name: 'description', required: false, desc: 'What you worked on. Optional, max 500 characters.' },
    { name: 'project_id', required: true, desc: 'Numeric ID of your project. Required — find it on the Projects page or in the list below.' },
    { name: 'date', required: true, desc: 'Day of the entry. Format YYYY-MM-DD, e.g. 2026-09-01.' },
    { name: 'start_time', required: true, desc: 'Start time, 24-hour HH:MM (e.g. 09:30). Seconds HH:MM:SS also accepted.' },
    { name: 'end_time', required: true, desc: 'End time, 24-hour HH:MM. Must be after start_time on the same date.' },
    { name: 'tags', required: false, desc: 'Optional. Multiple tags separated by ; — e.g. design;urgent. Tags are created automatically.' },
];
</script>

<template>
    <Head title="Import time entries" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-white">Import time entries</h2>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        Bulk-add entries from a CSV file. Nothing is imported if any row has an error.
                    </p>
                </div>
                <button
                    @click="downloadSample"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                >
                    <Download class="h-4 w-4" />
                    Download sample CSV
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Success -->
                <div
                    v-if="flashSuccess"
                    class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200"
                >
                    <CircleCheck class="mt-0.5 h-4 w-4 shrink-0" />
                    <p>{{ flashSuccess }}</p>
                </div>

                <!-- Upload card -->
                <div class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                        <Upload class="h-4 w-4 text-indigo-500" />
                        Upload CSV file
                    </h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        CSV file, max 2 MB, up to 1000 rows per import.
                    </p>

                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <label
                            class="flex flex-1 cursor-pointer items-center gap-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm transition hover:border-indigo-400 hover:bg-indigo-50/50 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-indigo-500 dark:hover:bg-indigo-500/5"
                        >
                            <FileText class="h-5 w-5 shrink-0 text-slate-400" />
                            <span class="truncate text-slate-600 dark:text-slate-300">
                                {{ selectedFileName || 'Choose a .csv file…' }}
                            </span>
                            <input ref="fileInput" type="file" accept=".csv,.txt" class="hidden" @change="onFileChange" />
                        </label>
                        <button
                            @click="submit"
                            :disabled="!form.file || form.processing"
                            class="rounded-xl bg-gradient-to-br from-indigo-500 to-fuchsia-500 px-5 py-2.5 text-sm font-medium text-white shadow-md shadow-indigo-500/25 transition hover:shadow-lg disabled:opacity-50"
                        >
                            {{ form.processing ? 'Importing…' : 'Import' }}
                        </button>
                    </div>
                    <p v-if="form.errors.file" class="mt-2 text-sm text-rose-600 dark:text-rose-400">
                        {{ form.errors.file }}
                    </p>

                    <!-- Row errors -->
                    <div v-if="importErrors.length > 0" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-500/30 dark:bg-rose-500/10">
                        <p class="flex items-center gap-2 text-sm font-medium text-rose-700 dark:text-rose-300">
                            <CircleAlert class="h-4 w-4" />
                            {{ importErrorCount }} row(s) need fixing — nothing was imported.
                        </p>
                        <ul class="mt-2 max-h-48 space-y-1 overflow-y-auto text-xs text-rose-600 dark:text-rose-300/90">
                            <li v-for="(err, i) in importErrors" :key="i" class="font-mono">• {{ err }}</li>
                        </ul>
                        <p v-if="importErrorCount > importErrors.length" class="mt-2 text-xs text-rose-500">
                            …and {{ importErrorCount - importErrors.length }} more. Fix the file and try again.
                        </p>
                    </div>
                </div>

                <!-- How-to card -->
                <div class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                        <Info class="h-4 w-4 text-indigo-500" />
                        How your CSV should look
                    </h3>

                    <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                        <table class="w-full min-w-[520px] text-left text-xs">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                                    <th class="px-3 py-2 font-medium">Column</th>
                                    <th class="px-3 py-2 font-medium">Required</th>
                                    <th class="px-3 py-2 font-medium">Format</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-for="col in columns" :key="col.name">
                                    <td class="px-3 py-2 font-mono font-semibold text-slate-800 dark:text-slate-100">{{ col.name }}</td>
                                    <td class="px-3 py-2">
                                        <span
                                            class="rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider"
                                            :class="col.required
                                                ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300'
                                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                                        >
                                            {{ col.required ? 'Yes' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ col.desc }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <ul class="mt-3 space-y-1.5 text-xs text-slate-500 dark:text-slate-400">
                        <li>• First row must be the header: <code class="rounded bg-slate-100 px-1 font-mono text-[11px] dark:bg-slate-800">description,project_id,date,start_time,end_time,tags</code></li>
                        <li>• <span class="font-medium text-slate-700 dark:text-slate-200">project_id is required</span> on every row — use the numeric ID shown on the
                            <Link :href="route('projects.index')" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">Projects page</Link>
                            (or the list below).
                        </li>
                        <li>• Times are treated as your local time (Asia/Tehran). The import creates finished entries only — no running timers.</li>
                        <li>• Tip: the <span class="font-medium">Export CSV</span> on the Reports page now includes <code class="rounded bg-slate-100 px-1 font-mono text-[11px] dark:bg-slate-800">project_id</code> and <code class="rounded bg-slate-100 px-1 font-mono text-[11px] dark:bg-slate-800">tags</code>, so an exported file can be re-imported directly.</li>
                    </ul>

                    <!-- Sample -->
                    <div class="mt-4">
                        <div class="mb-1.5 flex items-center justify-between">
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Example file</p>
                            <button
                                @click="copySample"
                                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            >
                                <Check v-if="copiedSample" class="h-3 w-3 text-emerald-500" />
                                <Copy v-else class="h-3 w-3" />
                                {{ copiedSample ? 'Copied!' : 'Copy' }}
                            </button>
                        </div>
                        <pre class="overflow-x-auto rounded-xl bg-slate-950 p-4 font-mono text-[11px] leading-relaxed text-slate-100 dark:bg-black/40">{{ sampleCsv }}</pre>
                    </div>
                </div>

                <!-- Project ID reference -->
                <div class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                        <Folder class="h-4 w-4 text-indigo-500" />
                        Your project IDs
                    </h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Click an ID to copy it. Same IDs are shown on the
                        <Link :href="route('projects.index')" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">Projects page</Link>.
                    </p>

                    <div v-if="projects.length === 0" class="mt-3 rounded-xl bg-slate-50 p-4 text-center text-xs text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        No projects yet.
                        <Link :href="route('projects.index')" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">Create one first</Link>.
                    </div>
                    <div v-else class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                        <div v-for="p in projects" :key="p.id" class="flex items-center justify-between gap-3 py-2">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: p.color || '#94a3b8' }"></span>
                                <span class="truncate text-sm text-slate-700 dark:text-slate-200">
                                    <span v-if="p.parent_name" class="text-slate-400 dark:text-slate-500">{{ p.parent_name }} / </span>{{ p.name }}
                                </span>
                                <span v-if="p.is_archived" class="rounded bg-slate-200 px-1.5 py-px text-[10px] font-medium uppercase tracking-wider text-slate-500 dark:bg-slate-700 dark:text-slate-400">Archived</span>
                            </div>
                            <button
                                @click="copyId(p.id)"
                                :title="`Copy ID ${p.id}`"
                                class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 font-mono text-xs font-semibold text-slate-600 transition hover:bg-indigo-100 hover:text-indigo-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-indigo-500/15 dark:hover:text-indigo-300"
                            >
                                <span class="text-[10px] font-normal uppercase text-slate-400">ID</span>
                                {{ p.id }}
                                <Check v-if="copiedId === p.id" class="h-3 w-3 text-emerald-500" />
                                <Copy v-else class="h-3 w-3 opacity-50" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
