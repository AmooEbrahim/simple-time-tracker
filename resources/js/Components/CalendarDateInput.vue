<script setup>
import { computed, onBeforeUnmount, ref, useAttrs, watch } from 'vue';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import {
    CALENDAR_TYPES,
    MONTHS,
    WEEKDAYS_SHORT,
    formatDate,
    getDateParts,
    isoFromCalendarParts,
    monthGrid,
    parseISODate,
    shiftMonth,
    todayISO,
} from '../lib/calendar.js';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    calendar: {
        type: String,
        default: 'gregorian',
    },
    weekStart: {
        type: [Number, String],
        default: 6,
    },
    min: {
        type: String,
        default: '',
    },
    max: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Select date',
    },
});

const emit = defineEmits(['update:modelValue']);

const attrs = useAttrs();
const rootRef = ref(null);
const triggerRef = ref(null);
const open = ref(false);
const todayDate = ref(todayISO());

const activeCalendar = computed(() =>
    CALENDAR_TYPES.includes(props.calendar) ? props.calendar : 'gregorian',
);
const isGregorian = computed(() => activeCalendar.value === 'gregorian');

const normalizedWeekStart = computed(() => {
    const value = Number(props.weekStart);
    if (!Number.isInteger(value)) return 6;
    return ((value % 7) + 7) % 7;
});

const minISO = computed(() => (parseISODate(props.min) ? props.min : ''));
const maxISO = computed(() => (parseISODate(props.max) ? props.max : ''));

const initialParts =
    getDateParts(props.modelValue, activeCalendar.value) ??
    getDateParts(todayDate.value, activeCalendar.value);

const viewYear = ref(initialParts?.year ?? 1400);
const viewMonth = ref(initialParts?.month ?? 1);

const displayValue = computed(() =>
    formatDate(props.modelValue, {
        calendar: activeCalendar.value,
        style: 'numeric',
    }),
);

const viewLabel = computed(
    () => `${MONTHS[activeCalendar.value][viewMonth.value - 1]} ${viewYear.value}`,
);

const weekdayLabels = computed(() =>
    Array.from(
        { length: 7 },
        (_, index) => WEEKDAYS_SHORT[(normalizedWeekStart.value + index) % 7],
    ),
);

const grid = computed(() =>
    monthGrid(
        viewYear.value,
        viewMonth.value,
        activeCalendar.value,
        normalizedWeekStart.value,
    ),
);

const canSelectToday = computed(
    () => !!todayDate.value && !isDisabled(todayDate.value),
);

// Tailwind utilities are only used as a fallback. Callers inside the app pass
// `class="dt-input w-full ..."`, which is forwarded to the control untouched.
const defaultControlClass = computed(() =>
    attrs.class
        ? ''
        : 'rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm transition-colors focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200',
);

function isDisabled(iso) {
    if (!iso) return true;
    if (minISO.value && iso < minISO.value) return true;
    if (maxISO.value && iso > maxISO.value) return true;
    return false;
}

const cells = computed(() => {
    const { daysInMonth, offset } = grid.value;
    const result = Array.from({ length: offset }, () => null);

    for (let day = 1; day <= daysInMonth; day += 1) {
        const iso = isoFromCalendarParts(
            viewYear.value,
            viewMonth.value,
            day,
            activeCalendar.value,
        );

        if (!iso) {
            result.push(null);
            continue;
        }

        result.push({
            day,
            iso,
            selected: iso === props.modelValue,
            isToday: iso === todayDate.value,
            disabled: isDisabled(iso),
            label: formatDate(iso, {
                calendar: activeCalendar.value,
                style: 'long',
                weekday: true,
            }),
        });
    }

    return result;
});

function syncViewToValue() {
    const parts = getDateParts(props.modelValue, activeCalendar.value);
    if (!parts) return;

    viewYear.value = parts.year;
    viewMonth.value = parts.month;
}

function openPicker() {
    todayDate.value = todayISO();

    const parts =
        getDateParts(props.modelValue, activeCalendar.value) ??
        getDateParts(todayDate.value, activeCalendar.value);

    if (parts) {
        viewYear.value = parts.year;
        viewMonth.value = parts.month;
    }

    open.value = true;
}

function closePicker({ focus = false } = {}) {
    open.value = false;
    if (focus) triggerRef.value?.focus();
}

function togglePicker() {
    if (open.value) {
        closePicker();
    } else {
        openPicker();
    }
}

function changeMonth(delta) {
    const next = shiftMonth(viewYear.value, viewMonth.value, delta);
    if (!next) return;

    viewYear.value = next.year;
    viewMonth.value = next.month;
}

function selectDate(cell) {
    if (!cell || cell.disabled) return;

    emit('update:modelValue', cell.iso);
    closePicker({ focus: true });
}

function selectToday() {
    if (!canSelectToday.value) return;

    emit('update:modelValue', todayDate.value);
    closePicker({ focus: true });
}

function onNativeInput(event) {
    emit('update:modelValue', event.target.value);
}

function onDocumentPointerDown(event) {
    if (!rootRef.value?.contains(event.target)) {
        closePicker();
    }
}

function onDocumentKeydown(event) {
    if (event.key === 'Escape') {
        closePicker({ focus: true });
    }
}

function addDocumentListeners() {
    if (typeof document === 'undefined') return;
    document.addEventListener('pointerdown', onDocumentPointerDown);
    document.addEventListener('keydown', onDocumentKeydown);
}

function removeDocumentListeners() {
    if (typeof document === 'undefined') return;
    document.removeEventListener('pointerdown', onDocumentPointerDown);
    document.removeEventListener('keydown', onDocumentKeydown);
}

watch(open, (isOpen) => {
    if (isOpen) {
        addDocumentListeners();
    } else {
        removeDocumentListeners();
    }
});

watch(
    () => props.modelValue,
    () => {
        syncViewToValue();
    },
);

watch(activeCalendar, () => {
    open.value = false;
    todayDate.value = todayISO();

    const parts =
        getDateParts(props.modelValue, activeCalendar.value) ??
        getDateParts(todayDate.value, activeCalendar.value);

    if (parts) {
        viewYear.value = parts.year;
        viewMonth.value = parts.month;
    }
});

onBeforeUnmount(removeDocumentListeners);
</script>

<template>
    <div ref="rootRef" class="relative">
        <input
            v-if="isGregorian"
            v-bind="$attrs"
            type="date"
            class="w-full"
            :class="defaultControlClass"
            :value="modelValue"
            :min="minISO || undefined"
            :max="maxISO || undefined"
            @input="onNativeInput"
        />

        <template v-else>
            <button
                ref="triggerRef"
                v-bind="$attrs"
                type="button"
                class="flex w-full cursor-pointer items-center justify-between gap-2 text-left tabular-nums disabled:cursor-not-allowed"
                :class="[
                    defaultControlClass,
                    displayValue ? '' : 'text-slate-400 dark:text-slate-500',
                ]"
                aria-haspopup="dialog"
                :aria-expanded="open ? 'true' : 'false'"
                @click="togglePicker"
            >
                <span class="min-w-0 truncate">{{ displayValue || placeholder }}</span>
                <CalendarDays class="h-4 w-4 shrink-0 opacity-60" aria-hidden="true" />
            </button>

            <transition
                enter-active-class="transition duration-100 ease-out"
                enter-from-class="scale-95 opacity-0"
                enter-to-class="scale-100 opacity-100"
                leave-active-class="transition duration-75 ease-in"
                leave-from-class="scale-100 opacity-100"
                leave-to-class="scale-95 opacity-0"
            >
                <div
                    v-if="open"
                    class="absolute left-0 z-50 mt-1 w-72 max-w-[calc(100vw-2rem)] rounded-xl border border-slate-200 bg-white p-3 shadow-xl dark:border-slate-700 dark:bg-slate-800"
                    role="dialog"
                    :aria-label="`Choose a ${activeCalendar} date`"
                    @keydown.esc.stop="closePicker({ focus: true })"
                >
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <button
                            type="button"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                            aria-label="Previous month"
                            @click="changeMonth(-1)"
                        >
                            <ChevronLeft class="h-4 w-4" aria-hidden="true" />
                        </button>

                        <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            {{ viewLabel }}
                        </div>

                        <button
                            type="button"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                            aria-label="Next month"
                            @click="changeMonth(1)"
                        >
                            <ChevronRight class="h-4 w-4" aria-hidden="true" />
                        </button>
                    </div>

                    <div class="grid grid-cols-7 gap-1">
                        <div
                            v-for="label in weekdayLabels"
                            :key="label"
                            class="flex h-7 items-center justify-center text-[11px] font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500"
                        >
                            {{ label }}
                        </div>
                    </div>

                    <div class="mt-1 grid grid-cols-7 justify-items-center gap-1">
                        <template v-for="(cell, index) in cells" :key="cell ? cell.iso : `blank-${index}`">
                            <span v-if="!cell"></span>
                            <button
                                v-else
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-xs font-medium transition-colors"
                                :class="[
                                    cell.selected
                                        ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                                        : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700',
                                    !cell.selected && cell.isToday
                                        ? 'ring-1 ring-inset ring-indigo-400 dark:ring-indigo-400'
                                        : '',
                                    cell.disabled
                                        ? 'cursor-not-allowed opacity-30 hover:bg-transparent dark:hover:bg-transparent'
                                        : '',
                                ]"
                                :disabled="cell.disabled"
                                :aria-label="cell.label"
                                :aria-current="cell.isToday ? 'date' : undefined"
                                :aria-selected="cell.selected ? 'true' : 'false'"
                                @click="selectDate(cell)"
                            >
                                {{ cell.day }}
                            </button>
                        </template>
                    </div>

                    <div
                        class="mt-2 flex items-center justify-end border-t border-slate-100 pt-2 dark:border-slate-700"
                    >
                        <button
                            type="button"
                            class="rounded-md px-2 py-1 text-xs font-medium text-indigo-600 transition-colors hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-40 dark:text-indigo-300 dark:hover:bg-indigo-500/10"
                            :disabled="!canSelectToday"
                            @click="selectToday"
                        >
                            Today
                        </button>
                    </div>
                </div>
            </transition>
        </template>
    </div>
</template>
