<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { ref, computed, onMounted, watch } from "vue";
import { Doughnut, Line } from "vue-chartjs";
import {
    Chart as ChartJS,
    ArcElement,
    Tooltip,
    Legend,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Filler,
} from "chart.js";
import {
    Clock,
    CalendarDays,
    Folder,
    Hash,
    Download,
    Upload,
    Sparkles,
    TrendingUp,
} from "lucide-vue-next";
import ProjectSelect from "@/Components/ProjectSelect.vue";

ChartJS.register(
    ArcElement,
    Tooltip,
    Legend,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Filler,
);

const props = defineProps({
    range: String,
    startDate: String,
    endDate: String,
    selectedProjectId: [Number, String],
    totalSeconds: Number,
    groupedByProject: Array,
    groupedByDate: Array,
    groupedByTag: Array,
    groupedBySubProject: Array,
    projects: Array,
});

const filterRange = ref(props.range);
const filterStartDate = ref(props.startDate);
const filterEndDate = ref(props.endDate);
const filterProjectId = ref(props.selectedProjectId || "");

const isDark = ref(false);
const updateDarkMode = () => {
    isDark.value = document.documentElement.classList.contains("dark");
};

onMounted(() => {
    updateDarkMode();
    const observer = new MutationObserver(updateDarkMode);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ["class"],
    });
});

const formatHM = (seconds) => {
    const s = Math.max(0, Math.floor(seconds || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
};

const totalDisplay = computed(() => formatHM(props.totalSeconds));

const totalEntries = computed(() =>
    props.groupedByProject.reduce((sum, g) => sum + g.entry_count, 0),
);

const dayCount = computed(() => {
    if (!props.startDate || !props.endDate) return 1;
    const start = new Date(props.startDate);
    const end = new Date(props.endDate);
    const diff = Math.max(1, Math.round((end - start) / 86400000) + 1);
    return diff;
});

const dailyAverage = computed(() =>
    formatHM(Math.round((props.totalSeconds || 0) / dayCount.value)),
);

const topProject = computed(() => props.groupedByProject[0] || null);

const applyFilters = () => {
    router.get(
        route("reports"),
        {
            range: filterRange.value,
            start: filterStartDate.value,
            end: filterEndDate.value,
            project_id: filterProjectId.value || null,
        },
        { preserveScroll: true },
    );
};

const setRange = (range) => {
    filterRange.value = range;
    if (range !== "custom") {
        router.get(
            route("reports"),
            {
                range,
                project_id: filterProjectId.value || null,
            },
            { preserveScroll: true },
        );
    }
};

watch(filterProjectId, () => {
    applyFilters();
});

const exportReport = () => {
    const params = new URLSearchParams({
        start: filterStartDate.value,
        end: filterEndDate.value,
    });
    if (filterProjectId.value) {
        params.append("project_id", filterProjectId.value);
    }
    window.open(`${route("reports.export")}?${params.toString()}`, "_blank");
};

const getProjectColor = (project) => project?.color || "#94a3b8";
const getProjectName = (project) => project?.name || "No project";

const maxTagSeconds = computed(() =>
    Math.max(...props.groupedByTag.map((g) => g.total_seconds), 1),
);

const maxSubProjectSeconds = computed(() => {
    let max = 1;
    for (const group of props.groupedBySubProject || []) {
        if (group.total_seconds > max) max = group.total_seconds;
        for (const sub of group.sub_projects || []) {
            if (sub.total_seconds > max) max = sub.total_seconds;
        }
    }
    return max;
});

// --- Donut chart for projects ---
const doughnutData = computed(() => ({
    labels: props.groupedByProject.map((g) => getProjectName(g.project)),
    datasets: [
        {
            data: props.groupedByProject.map((g) => g.total_seconds),
            backgroundColor: props.groupedByProject.map((g) =>
                getProjectColor(g.project),
            ),
            borderWidth: 0,
            spacing: 2,
            hoverOffset: 8,
        },
    ],
}));

const doughnutOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    cutout: "70%",
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: isDark.value ? "#0f172a" : "#ffffff",
            titleColor: isDark.value ? "#f1f5f9" : "#0f172a",
            bodyColor: isDark.value ? "#cbd5e1" : "#475569",
            borderColor: isDark.value ? "#1e293b" : "#e2e8f0",
            borderWidth: 1,
            padding: 12,
            displayColors: true,
            callbacks: {
                label: (ctx) => ` ${formatHM(ctx.parsed)}`,
            },
        },
    },
}));

// --- Line chart for date timeline ---
const sortedByDate = computed(() =>
    [...props.groupedByDate].sort((a, b) => a.date.localeCompare(b.date)),
);

const lineData = computed(() => {
    const labels = sortedByDate.value.map((g) =>
        new Date(g.date).toLocaleDateString("en-US", {
            month: "short",
            day: "numeric",
        }),
    );
    const data = sortedByDate.value.map(
        (g) => +(g.total_seconds / 3600).toFixed(2),
    );

    return {
        labels,
        datasets: [
            {
                label: "Hours",
                data,
                borderColor: "#6366f1",
                backgroundColor: (ctx) => {
                    const { chart } = ctx;
                    const { ctx: c, chartArea } = chart;
                    if (!chartArea) return "rgba(99,102,241,0.15)";
                    const grad = c.createLinearGradient(
                        0,
                        chartArea.top,
                        0,
                        chartArea.bottom,
                    );
                    grad.addColorStop(0, "rgba(99,102,241,0.35)");
                    grad.addColorStop(1, "rgba(99,102,241,0)");
                    return grad;
                },
                fill: true,
                tension: 0.4,
                borderWidth: 2.5,
                pointBackgroundColor: "#6366f1",
                pointBorderColor: isDark.value ? "#0f172a" : "#ffffff",
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
            },
        ],
    };
});

const lineOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: isDark.value ? "#0f172a" : "#ffffff",
            titleColor: isDark.value ? "#f1f5f9" : "#0f172a",
            bodyColor: isDark.value ? "#cbd5e1" : "#475569",
            borderColor: isDark.value ? "#1e293b" : "#e2e8f0",
            borderWidth: 1,
            padding: 12,
            callbacks: {
                label: (ctx) => ` ${formatHM(Math.round(ctx.parsed.y * 3600))}`,
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: {
                color: isDark.value ? "#94a3b8" : "#64748b",
                font: { size: 11 },
                maxRotation: 0,
                autoSkip: true,
            },
            border: { display: false },
        },
        y: {
            grid: {
                color: isDark.value
                    ? "rgba(255,255,255,0.05)"
                    : "rgba(15,23,42,0.05)",
                drawTicks: false,
            },
            ticks: {
                color: isDark.value ? "#94a3b8" : "#64748b",
                font: { size: 11 },
                callback: (v) => `${v}h`,
                padding: 8,
            },
            border: { display: false },
            beginAtZero: true,
        },
    },
}));

import { specialOffDays } from "@/config/offDays.js";

const weekendHighlightPlugin = {
    id: "weekendHighlight",
    beforeDraw(chart) {
        const {
            ctx,
            chartArea: { top, bottom, left, right },
            scales: { x },
        } = chart;
        const entries = sortedByDate.value;
        if (!entries.length) return;

        ctx.save();

        for (let i = 0; i < entries.length; i++) {
            const dateStr = entries[i].date;
            const day = new Date(dateStr).getDay(); // 0=Sun, 4=Thu, 5=Fri
            const isSpecialOff = specialOffDays.includes(dateStr);
            const isWeekend = day === 4 || day === 5;

            if (!isSpecialOff && !isWeekend) continue;

            ctx.fillStyle = isSpecialOff
                ? isDark.value
                    ? "rgba(244,63,94,0.22)"
                    : "rgba(244,63,94,0.16)"
                : isDark.value
                  ? "rgba(99,102,241,0.22)"
                  : "rgba(99,102,241,0.16)";

            const xStart =
                i === 0
                    ? left
                    : (x.getPixelForValue(i - 1) + x.getPixelForValue(i)) / 2;
            const xEnd =
                i === entries.length - 1
                    ? right
                    : (x.getPixelForValue(i) + x.getPixelForValue(i + 1)) / 2;
            ctx.fillRect(xStart, top, xEnd - xStart, bottom - top);
        }

        ctx.restore();
    },
};

const ranges = [
    { value: "today", label: "Today" },
    { value: "week", label: "Week" },
    { value: "month", label: "Month" },
    { value: "year", label: "Year" },
    { value: "custom", label: "Custom" },
];
</script>

<template>
    <Head title="Reports" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2
                        class="text-xl font-semibold tracking-tight text-slate-900 dark:text-white"
                    >
                        Reports
                    </h2>
                    <p
                        class="mt-0.5 text-sm text-slate-500 dark:text-slate-400"
                    >
                        See where your time goes across projects, days, and
                        tags.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a
                        :href="route('import')"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        <Upload class="h-4 w-4" />
                        Import CSV
                    </a>
                    <button
                        @click="exportReport"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        <Download class="h-4 w-4" />
                        Export CSV
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Filters -->
                <div
                    class="rounded-2xl border border-slate-200/70 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex flex-wrap items-center gap-3">
                        <div
                            class="inline-flex rounded-lg bg-slate-100 p-1 dark:bg-slate-800"
                        >
                            <button
                                v-for="r in ranges"
                                :key="r.value"
                                @click="setRange(r.value)"
                                class="rounded-md px-3 py-1.5 text-xs font-medium transition"
                                :class="
                                    filterRange === r.value
                                        ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white'
                                        : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                                "
                            >
                                {{ r.label }}
                            </button>
                        </div>

                        <div
                            v-if="filterRange === 'custom'"
                            class="flex items-center gap-2"
                        >
                            <input
                                v-model="filterStartDate"
                                type="date"
                                class="rounded-lg border-slate-200 bg-slate-50 text-xs dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            />
                            <span class="text-xs text-slate-400">→</span>
                            <input
                                v-model="filterEndDate"
                                type="date"
                                class="rounded-lg border-slate-200 bg-slate-50 text-xs dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            />
                            <button
                                @click="applyFilters"
                                class="rounded-lg bg-gradient-to-br from-indigo-500 to-fuchsia-500 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:shadow-md"
                            >
                                Apply
                            </button>
                        </div>

                        <div class="ml-auto">
                            <ProjectSelect
                                v-model="filterProjectId"
                                :projects="projects"
                                placeholder="All projects"
                                :allow-empty="true"
                                class="w-48"
                            />
                        </div>
                    </div>
                </div>

                <!-- KPI cards -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div
                        class="rounded-2xl border border-slate-200/70 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div class="flex items-center justify-between">
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400"
                            >
                                <Clock class="h-4 w-4" />
                            </div>
                        </div>
                        <p
                            class="mt-3 text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400"
                        >
                            Total time
                        </p>
                        <p
                            class="mt-0.5 font-mono text-2xl font-semibold tabular-nums text-slate-900 dark:text-white"
                        >
                            {{ totalDisplay }}
                        </p>
                    </div>

                    <div
                        class="rounded-2xl border border-slate-200/70 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div class="flex items-center justify-between">
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded-xl bg-fuchsia-100 text-fuchsia-600 dark:bg-fuchsia-500/15 dark:text-fuchsia-400"
                            >
                                <TrendingUp class="h-4 w-4" />
                            </div>
                        </div>
                        <p
                            class="mt-3 text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400"
                        >
                            Daily avg
                        </p>
                        <p
                            class="mt-0.5 font-mono text-2xl font-semibold tabular-nums text-slate-900 dark:text-white"
                        >
                            {{ dailyAverage }}
                        </p>
                    </div>

                    <div
                        class="rounded-2xl border border-slate-200/70 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div class="flex items-center justify-between">
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400"
                            >
                                <Folder class="h-4 w-4" />
                            </div>
                        </div>
                        <p
                            class="mt-3 text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400"
                        >
                            Top project
                        </p>
                        <p
                            class="mt-0.5 truncate text-base font-semibold text-slate-900 dark:text-white"
                        >
                            {{
                                topProject
                                    ? getProjectName(topProject.project)
                                    : "—"
                            }}
                        </p>
                        <p
                            v-if="topProject"
                            class="text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ formatHM(topProject.total_seconds) }}
                        </p>
                    </div>

                    <div
                        class="rounded-2xl border border-slate-200/70 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div class="flex items-center justify-between">
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400"
                            >
                                <Sparkles class="h-4 w-4" />
                            </div>
                        </div>
                        <p
                            class="mt-3 text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400"
                        >
                            Entries
                        </p>
                        <p
                            class="mt-0.5 text-2xl font-semibold text-slate-900 dark:text-white"
                        >
                            {{ totalEntries }}
                        </p>
                    </div>
                </div>

                <!-- Over time: full width -->
                <div
                    class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h3
                            class="text-sm font-semibold text-slate-900 dark:text-white"
                        >
                            Over time
                        </h3>
                        <span
                            class="text-xs text-slate-500 dark:text-slate-400"
                        >
                            <CalendarDays class="mr-1 inline h-3 w-3" />
                            {{ startDate }} – {{ endDate }}
                        </span>
                    </div>

                    <div
                        v-if="sortedByDate.length === 0"
                        class="flex h-64 items-center justify-center text-sm text-slate-500 dark:text-slate-400"
                    >
                        No data for this period.
                    </div>
                    <div v-else class="h-64">
                        <Line
                            :data="lineData"
                            :options="lineOptions"
                            :plugins="[weekendHighlightPlugin]"
                        />
                    </div>
                </div>

                <!-- By project + By tag -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Donut: by project -->
                    <div
                        class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <h3
                                class="text-sm font-semibold text-slate-900 dark:text-white"
                            >
                                By project
                            </h3>
                            <span
                                class="text-xs text-slate-500 dark:text-slate-400"
                                >{{ groupedByProject.length }} active</span
                            >
                        </div>

                        <div
                            v-if="groupedByProject.length === 0"
                            class="flex h-64 items-center justify-center text-sm text-slate-500 dark:text-slate-400"
                        >
                            No data for this period.
                        </div>
                        <template v-else>
                            <div class="relative mx-auto h-56 w-56">
                                <Doughnut
                                    :data="doughnutData"
                                    :options="doughnutOptions"
                                />
                                <div
                                    class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center"
                                >
                                    <p
                                        class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400"
                                    >
                                        Total
                                    </p>
                                    <p
                                        class="font-mono text-xl font-semibold tabular-nums text-slate-900 dark:text-white"
                                    >
                                        {{ totalDisplay }}
                                    </p>
                                </div>
                            </div>
                            <div class="mt-4 space-y-2">
                                <div
                                    v-for="group in groupedByProject"
                                    :key="group.project?.id || 'none'"
                                    class="flex items-center justify-between text-xs"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-2"
                                    >
                                        <span
                                            class="h-2.5 w-2.5 rounded-full"
                                            :style="{
                                                backgroundColor:
                                                    getProjectColor(
                                                        group.project,
                                                    ),
                                            }"
                                        ></span>
                                        <span
                                            class="truncate font-medium text-slate-700 dark:text-slate-200"
                                            >{{
                                                getProjectName(group.project)
                                            }}</span
                                        >
                                    </div>
                                    <span
                                        class="font-mono tabular-nums text-slate-500 dark:text-slate-400"
                                        >{{
                                            formatHM(group.total_seconds)
                                        }}</span
                                    >
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Sub-projects grid -->
                    <div
                        class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-2"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <h3
                                class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white"
                            >
                                <Folder class="h-4 w-4 text-slate-400" />
                                By sub-project
                            </h3>
                            <span
                                class="text-xs text-slate-500 dark:text-slate-400"
                                >{{ (groupedBySubProject || []).length }} projects</span
                            >
                        </div>

                        <div
                            v-if="!groupedBySubProject || groupedBySubProject.length === 0"
                            class="py-6 text-center text-sm text-slate-500 dark:text-slate-400"
                        >
                            No data for this period.
                        </div>
                        <div
                            v-else
                            class="space-y-4"
                        >
                            <div
                                v-for="group in groupedBySubProject"
                                :key="group.parent?.id || 'no_project'"
                                class="rounded-xl border border-slate-200/70 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/40"
                            >
                                <div class="mb-3 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="h-3 w-3 rounded-full"
                                            :style="{ backgroundColor: getProjectColor(group.parent) }"
                                        ></span>
                                        <span class="text-sm font-semibold text-slate-900 dark:text-white">
                                            {{ getProjectName(group.parent) }}
                                        </span>
                                    </div>
                                    <span
                                        class="font-mono text-sm tabular-nums text-slate-600 dark:text-slate-400"
                                    >
                                        {{ formatHM(group.total_seconds) }}
                                    </span>
                                </div>

                                <div class="space-y-2">
                                    <div
                                        v-for="sub in group.sub_projects"
                                        :key="sub.sub_project?.id || 'none'"
                                        class="rounded-lg bg-white p-2.5 dark:bg-slate-900/50"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div class="flex min-w-0 items-center gap-2">
                                                <span
                                                    class="h-2 w-2 rounded-full"
                                                    :style="{ backgroundColor: getProjectColor(sub.sub_project) }"
                                                ></span>
                                                <span class="truncate text-xs font-medium text-slate-700 dark:text-slate-200">
                                                    {{ sub.sub_project?.name || 'No project' }}
                                                </span>
                                            </div>
                                            <span
                                                class="font-mono text-xs tabular-nums text-slate-500 dark:text-slate-400"
                                            >
                                                {{ formatHM(sub.total_seconds) }}
                                            </span>
                                        </div>
                                        <div
                                            class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                                        >
                                            <div
                                                class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-fuchsia-500 transition-all"
                                                :style="{
                                                    width: `${(sub.total_seconds / maxSubProjectSeconds) * 100}%`,
                                                }"
                                            ></div>
                                        </div>
                                        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                                            {{ sub.entry_count }} {{ sub.entry_count === 1 ? 'entry' : 'entries' }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="mt-2 h-1.5 overflow-hidden rounded-full bg-white dark:bg-slate-800"
                                >
                                    <div
                                        class="h-full rounded-full transition-all"
                                        :style="{
                                            width: `${(group.total_seconds / maxSubProjectSeconds) * 100}%`,
                                            backgroundColor: getProjectColor(group.parent),
                                        }"
                                    ></div>
                                </div>
                                <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                                    {{ group.entry_count }} {{ group.entry_count === 1 ? 'entry' : 'entries' }} total
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
