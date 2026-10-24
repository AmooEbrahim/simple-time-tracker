<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { HOLIDAY_COUNTRIES } from '@/config/holidays/index.js';
import { DEFAULT_SETTINGS, WEEKDAYS, WEEKDAYS_SHORT, formatDate, todayISO } from '@/lib/calendar.js';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import {
    CalendarDays,
    CalendarRange,
    CircleCheck,
    PartyPopper,
    Save,
    X,
} from 'lucide-vue-next';

const props = defineProps({
    settings: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const today = todayISO();

const form = useForm({
    calendar: props.settings?.calendar ?? DEFAULT_SETTINGS.calendar,
    week_start: Number(props.settings?.week_start ?? DEFAULT_SETTINGS.week_start),
    weekend_days: (props.settings?.weekend_days ?? DEFAULT_SETTINGS.weekend_days).map(Number),
    show_holidays: Boolean(props.settings?.show_holidays ?? DEFAULT_SETTINGS.show_holidays),
    holiday_country: props.settings?.holiday_country ?? DEFAULT_SETTINGS.holiday_country,
});

const calendarOptions = [
    {
        value: 'gregorian',
        name: 'Gregorian',
        description: 'The international civil calendar, January to December.',
        example: formatDate(today, { calendar: 'gregorian', style: 'long', weekday: true }),
    },
    {
        value: 'jalali',
        name: 'Jalali (Shamsi)',
        description: 'The Solar Hijri calendar used in Iran and Afghanistan.',
        example: formatDate(today, { calendar: 'jalali', style: 'long', weekday: true }),
    },
    {
        value: 'hijri',
        name: 'Hijri (Qamari)',
        description: 'The lunar Hijri calendar used for Islamic dates.',
        example: formatDate(today, { calendar: 'hijri', style: 'long', weekday: true }),
    },
];

// Saturday-first ordering keeps the week UI aligned with the app's default.
const WEEK_ORDER = [6, 0, 1, 2, 3, 4, 5];

const weekStartOptions = WEEK_ORDER.map((value) => ({
    value,
    label: WEEKDAYS[value],
}));

const weekendDayOptions = WEEK_ORDER.map((value) => ({
    value,
    label: WEEKDAYS_SHORT[value],
    fullLabel: WEEKDAYS[value],
}));

const isWeekend = (day) => form.weekend_days.includes(day);

const toggleWeekendDay = (day) => {
    const days = [...form.weekend_days];
    const index = days.indexOf(day);

    if (index >= 0) {
        days.splice(index, 1);
    } else {
        days.push(day);
    }

    form.weekend_days = days.sort((a, b) => a - b);
};

const flashSuccess = computed(() => page.props.flash?.success);

const successDismissed = ref(false);

let successTimer = null;

const clearSuccessTimer = () => {
    if (successTimer) {
        clearTimeout(successTimer);
        successTimer = null;
    }
};

// Watching the flash object (not the message) means a repeated "Settings saved."
// after another save still re-opens the banner.
watch(
    () => page.props.flash,
    (flash) => {
        clearSuccessTimer();
        successDismissed.value = false;

        if (!flash?.success) return;

        successTimer = setTimeout(() => {
            successDismissed.value = true;
        }, 5000);
    },
    { immediate: true },
);

onBeforeUnmount(clearSuccessTimer);

const dismissSuccess = () => {
    clearSuccessTimer();
    successDismissed.value = true;
};

const submit = () => {
    form.patch(route('settings.update'), { preserveScroll: true });
};
</script>

<template>
    <Head title="Settings" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-white">
                    Settings
                </h2>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    Choose how dates, weeks, and holidays appear across Tempo.
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <!-- Success banner -->
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="-translate-y-1 opacity-0"
                    enter-to-class="translate-y-0 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="flashSuccess && !successDismissed"
                        class="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200"
                        role="status"
                    >
                        <CircleCheck class="mt-0.5 h-4 w-4 shrink-0" />
                        <p class="flex-1">{{ flashSuccess }}</p>
                        <button
                            type="button"
                            class="-m-1 rounded-lg p-1 text-emerald-600 transition hover:bg-emerald-100 dark:text-emerald-300 dark:hover:bg-emerald-500/20"
                            aria-label="Dismiss"
                            @click="dismissSuccess"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </Transition>

                <form class="space-y-6" @submit.prevent="submit">
                    <!-- Calendar -->
                    <section class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                            <CalendarDays class="h-4 w-4 text-indigo-500" />
                            Calendar
                        </h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Pick the calendar used to display dates everywhere in Tempo.
                        </p>

                        <div
                            class="mt-4 grid gap-3 sm:grid-cols-3"
                            role="radiogroup"
                            aria-label="Display calendar"
                        >
                            <button
                                v-for="option in calendarOptions"
                                :key="option.value"
                                type="button"
                                role="radio"
                                :aria-checked="form.calendar === option.value"
                                class="flex flex-col rounded-xl border p-4 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
                                :class="form.calendar === option.value
                                    ? 'border-indigo-500 bg-indigo-50/70 ring-2 ring-indigo-500/40 dark:border-indigo-400 dark:bg-indigo-500/10 dark:ring-indigo-400/40'
                                    : 'border-slate-200 bg-white hover:border-indigo-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-500/50 dark:hover:bg-slate-800/60'"
                                @click="form.calendar = option.value"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                            {{ option.name }}
                                        </p>
                                        <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                            {{ option.description }}
                                        </p>
                                    </div>
                                    <span
                                        class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border transition"
                                        :class="form.calendar === option.value
                                            ? 'border-indigo-500 dark:border-indigo-400'
                                            : 'border-slate-300 dark:border-slate-600'"
                                    >
                                        <span
                                            v-if="form.calendar === option.value"
                                            class="h-2 w-2 rounded-full bg-indigo-500 dark:bg-indigo-400"
                                        />
                                    </span>
                                </div>

                                <div class="mt-3 rounded-lg bg-slate-50 px-2.5 py-2 text-[11px] font-medium text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                                    Today: {{ option.example }}
                                </div>

                                <p
                                    v-if="option.value === 'hijri'"
                                    class="mt-2 text-[11px] leading-relaxed text-slate-400 dark:text-slate-500"
                                >
                                    Uses the Umm al-Qura lunar calendar; local sightings can differ by a day.
                                </p>
                            </button>
                        </div>

                        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                            Dates are always stored as Gregorian ISO (YYYY-MM-DD). This setting only changes how they are displayed.
                        </p>

                        <InputError class="mt-2" :message="form.errors.calendar" />
                    </section>

                    <!-- Week -->
                    <section class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                            <CalendarRange class="h-4 w-4 text-indigo-500" />
                            Week
                        </h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            These preferences affect week grouping and report shading.
                        </p>

                        <div class="mt-4 sm:max-w-xs">
                            <label
                                for="week_start"
                                class="block text-xs font-medium text-slate-600 dark:text-slate-300"
                            >
                                Week starts on
                            </label>
                            <select
                                id="week_start"
                                v-model.number="form.week_start"
                                class="mt-1.5 w-full rounded-xl border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                            >
                                <option
                                    v-for="option in weekStartOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.week_start" />
                        </div>

                        <div class="mt-5">
                            <p class="text-xs font-medium text-slate-600 dark:text-slate-300">
                                Weekend days
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                Select any number of days, or none.
                            </p>

                            <div class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-7">
                                <button
                                    v-for="day in weekendDayOptions"
                                    :key="day.value"
                                    type="button"
                                    :aria-pressed="isWeekend(day.value)"
                                    :title="day.fullLabel"
                                    class="rounded-xl border px-2 py-2 text-xs font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
                                    :class="isWeekend(day.value)
                                        ? 'border-indigo-500 bg-indigo-500 text-white shadow-sm shadow-indigo-500/25 dark:border-indigo-400 dark:bg-indigo-500'
                                        : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-indigo-500/50 dark:hover:bg-slate-800/60'"
                                    @click="toggleWeekendDay(day.value)"
                                >
                                    {{ day.label }}
                                </button>
                            </div>
                            <InputError class="mt-2" :message="form.errors.weekend_days" />
                        </div>
                    </section>

                    <!-- Holidays -->
                    <section class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                            <PartyPopper class="h-4 w-4 text-indigo-500" />
                            Holidays
                        </h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Highlight official holidays on calendars and reports.
                        </p>

                        <label
                            class="mt-4 flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3 transition hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-800/40 dark:hover:bg-slate-800/70"
                        >
                            <span>
                                <span class="block text-sm font-medium text-slate-800 dark:text-slate-100">
                                    Show official holidays
                                </span>
                                <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">
                                    Shade holidays in reports and show their names on hover.
                                </span>
                            </span>
                            <span class="relative inline-flex shrink-0 items-center">
                                <input
                                    v-model="form.show_holidays"
                                    type="checkbox"
                                    class="peer sr-only"
                                />
                                <span
                                    class="h-6 w-11 rounded-full bg-slate-200 transition peer-checked:bg-indigo-500 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 peer-focus-visible:ring-offset-2 dark:bg-slate-700 dark:peer-focus-visible:ring-offset-slate-900"
                                />
                                <span
                                    class="pointer-events-none absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition peer-checked:translate-x-5"
                                />
                            </span>
                        </label>
                        <InputError class="mt-2" :message="form.errors.show_holidays" />

                        <div class="mt-4 sm:max-w-xs">
                            <label
                                for="holiday_country"
                                class="block text-xs font-medium text-slate-600 dark:text-slate-300"
                            >
                                Holiday country
                            </label>
                            <select
                                id="holiday_country"
                                v-model="form.holiday_country"
                                class="mt-1.5 w-full rounded-xl border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                            >
                                <option
                                    v-for="country in HOLIDAY_COUNTRIES"
                                    :key="country.code"
                                    :value="country.code"
                                >
                                    {{ country.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.holiday_country" />
                        </div>

                        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                            Iran official holidays are bundled. More countries can be added later.
                        </p>
                    </section>

                    <!-- Save bar -->
                    <div class="rounded-2xl border border-slate-200/70 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Changes apply across Tempo as soon as you save.
                            </p>
                            <div class="flex items-center gap-3">
                                <Transition
                                    enter-active-class="transition duration-150 ease-out"
                                    enter-from-class="opacity-0"
                                    leave-active-class="transition duration-100 ease-in"
                                    leave-to-class="opacity-0"
                                >
                                    <p
                                        v-if="form.recentlySuccessful"
                                        class="text-sm font-medium text-emerald-600 dark:text-emerald-400"
                                    >
                                        Saved.
                                    </p>
                                </Transition>

                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-br from-indigo-500 to-fuchsia-500 px-5 py-2.5 text-sm font-medium text-white shadow-md shadow-indigo-500/25 transition hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-offset-slate-900"
                                >
                                    <Save class="h-4 w-4" />
                                    {{ form.processing ? 'Saving…' : 'Save settings' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
