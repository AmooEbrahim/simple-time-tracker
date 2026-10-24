/**
 * Calendar helpers for the three supported display calendars.
 *
 * Storage/transport is always a Gregorian ISO date (`YYYY-MM-DD`). Jalali and
 * Hijri are display-only projections of that date.
 *
 * Timezone notes:
 * - `YYYY-MM-DD` is a calendar date, not an instant. We never pass it to
 *   `new Date(string)`; `parseISODate()` builds the date from numeric parts.
 * - `CalendarDate#toDate('UTC')` is only used for weekday math and for the
 *   optional time-of-day formatter.
 */

import {
    CalendarDate,
    GregorianCalendar,
    IslamicUmalquraCalendar,
    PersianCalendar,
    today as todayInZone,
    toCalendar,
} from '@internationalized/date';

/** Supported calendar names used throughout the app. */
export const CALENDAR_TYPES = ['gregorian', 'jalali', 'hijri'];

/** Default preferences shared by pages before/without server settings. */
export const DEFAULT_SETTINGS = Object.freeze({
    calendar: 'jalali',
    week_start: 6,
    weekend_days: Object.freeze([4, 5]),
    show_holidays: true,
    holiday_country: 'IR',
});

/** Unicode/CLDR calendar identifiers for each supported calendar. */
export const CALENDAR_IDS = {
    gregorian: 'gregory',
    jalali: 'persian',
    hijri: 'islamic-umalqura',
};

/** Full English month names, indexed by calendar then month (1-based month - 1). */
export const MONTHS = {
    gregorian: [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    ],
    jalali: [
        'Farvardin',
        'Ordibehesht',
        'Khordad',
        'Tir',
        'Mordad',
        'Shahrivar',
        'Mehr',
        'Aban',
        'Azar',
        'Dey',
        'Bahman',
        'Esfand',
    ],
    hijri: [
        'Muharram',
        'Safar',
        'Rabi al-Awwal',
        'Rabi al-Thani',
        'Jumada al-Awwal',
        'Jumada al-Thani',
        'Rajab',
        'Shaban',
        'Ramadan',
        'Shawwal',
        'Dhu al-Qadah',
        'Dhu al-Hijjah',
    ],
};

/** Compact English month names, for axes/grids where space is tight. */
export const MONTHS_SHORT = {
    gregorian: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    jalali: ['Far', 'Ord', 'Kho', 'Tir', 'Mor', 'Sha', 'Meh', 'Aba', 'Aza', 'Dey', 'Bah', 'Esf'],
    hijri: [
        'Muh',
        'Saf',
        'Rab I',
        'Rab II',
        'Jum I',
        'Jum II',
        'Raj',
        'Sha',
        'Ram',
        'Shaw',
        'Dhu Q',
        'Dhu H',
    ],
};

/** Full English weekday names, Sunday-first. */
export const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

/** Compact English weekday names, Sunday-first. */
export const WEEKDAYS_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const GREGORIAN = new GregorianCalendar();
const CALENDARS = {
    gregorian: GREGORIAN,
    jalali: new PersianCalendar(),
    hijri: new IslamicUmalquraCalendar(),
};

const ISO_DATE_PATTERN = /^(\d{4})-(\d{2})-(\d{2})$/;

/**
 * Normalize an unknown calendar name to a supported one. Unknown values fall
 * back to Gregorian so UI never crashes on bad stored settings.
 *
 * @param {string} calendar
 * @returns {'gregorian'|'jalali'|'hijri'}
 */
function resolveCalendarName(calendar) {
    return CALENDAR_TYPES.includes(calendar) ? calendar : 'gregorian';
}

/**
 * @param {number|string} value
 * @returns {number} 0 (Sunday) through 6 (Saturday)
 */
function normalizeWeekStart(value) {
    const number = Number(value);
    if (!Number.isInteger(number)) return 6;
    return ((number % 7) + 7) % 7;
}

/**
 * @param {number} value
 * @param {number} [length=2]
 * @returns {string}
 */
function pad(value, length = 2) {
    return String(value).padStart(length, '0');
}

/**
 * Today's Gregorian ISO date in the given timezone.
 *
 * @param {string} [timeZone='Asia/Tehran']
 * @returns {string} `YYYY-MM-DD`
 */
export function todayISO(timeZone = 'Asia/Tehran') {
    try {
        return todayInZone(timeZone).toString();
    } catch {
        // Invalid timezone: keep the app usable rather than throwing in render.
        return todayInZone('UTC').toString();
    }
}

/**
 * Strictly parse a Gregorian `YYYY-MM-DD` string.
 *
 * Rejects non-strings, non-padded values, impossible dates (e.g. 2026-02-30),
 * and out-of-range months. Returns a Gregorian `CalendarDate` or `null`.
 *
 * @param {unknown} iso
 * @returns {import('@internationalized/date').CalendarDate|null}
 */
export function parseISODate(iso) {
    if (typeof iso !== 'string') return null;

    const match = ISO_DATE_PATTERN.exec(iso);
    if (!match) return null;

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);

    if (month < 1 || month > 12 || day < 1 || day > 31) return null;

    const date = new CalendarDate(year, month, day);

    // CalendarDate constrains invalid values (2026-02-30 becomes 2026-02-28),
    // so compare the fields back to the input to detect a silent adjustment.
    if (date.year !== year || date.month !== month || date.day !== day) return null;

    return date;
}

/**
 * Calendar-specific parts for a Gregorian ISO date.
 *
 * @param {string} iso
 * @param {'gregorian'|'jalali'|'hijri'} [calendar='gregorian']
 * @returns {{
 *   year: number,
 *   month: number,
 *   day: number,
 *   monthLong: string,
 *   monthShort: string,
 *   weekdayLong: string,
 *   weekdayShort: string,
 * }|null}
 */
export function getDateParts(iso, calendar = 'gregorian') {
    const date = parseISODate(iso);
    if (!date) return null;

    const name = resolveCalendarName(calendar);
    const converted = toCalendar(date, CALENDARS[name]);
    const monthIndex = converted.month - 1;
    const weekday = weekdayOfISO(iso);

    return {
        year: converted.year,
        month: converted.month,
        day: converted.day,
        monthLong: MONTHS[name][monthIndex] ?? String(converted.month),
        monthShort: MONTHS_SHORT[name][monthIndex] ?? String(converted.month),
        weekdayLong: WEEKDAYS[weekday] ?? '',
        weekdayShort: WEEKDAYS_SHORT[weekday] ?? '',
    };
}

/**
 * Format a Gregorian ISO date for display.
 *
 * Styles:
 * - `long`: `October 24, 2026` / `Aban 2, 1405`
 * - `short`: `Oct 24, 2026` / `Aban 2, 1405` (Jalali/Hijri keep the full,
 *   already-compact month name, matching the product copy)
 * - `numeric`: `2026-10-24` / `1405/08/02`
 *
 * `weekday` is ignored for the numeric style. `year: false` omits the year.
 *
 * @param {string} iso
 * @param {{
 *   calendar?: 'gregorian'|'jalali'|'hijri',
 *   style?: 'long'|'short'|'numeric',
 *   weekday?: boolean,
 *   year?: boolean,
 * }} [options]
 * @returns {string}
 */
export function formatDate(
    iso,
    { calendar = 'gregorian', style = 'long', weekday = false, year = true } = {},
) {
    const parts = getDateParts(iso, calendar);
    if (!parts) return '';

    const name = resolveCalendarName(calendar);

    if (style === 'numeric') {
        if (name === 'gregorian') {
            return `${pad(parts.year, 4)}-${pad(parts.month)}-${pad(parts.day)}`;
        }
        return `${pad(parts.year, 4)}/${pad(parts.month)}/${pad(parts.day)}`;
    }

    const monthName =
        style === 'short' && name === 'gregorian' ? parts.monthShort : parts.monthLong;
    const dayPart = `${monthName} ${parts.day}`;
    const withYear = year ? `${dayPart}, ${parts.year}` : dayPart;

    if (!weekday) return withYear;

    const weekdayName = style === 'short' ? parts.weekdayShort : parts.weekdayLong;
    return `${weekdayName}, ${withYear}`;
}

/**
 * Format an inclusive date range.
 *
 * Examples (style `short`):
 * - `Aban 2 – Aban 8, 1405`
 * - `Aban 30 – Azar 6, 1405`
 * - `Esfand 29, 1404 – Farvardin 2, 1405`
 *
 * @param {string} startISO
 * @param {string} endISO
 * @param {{ calendar?: 'gregorian'|'jalali'|'hijri', style?: 'long'|'short'|'numeric' }} [options]
 * @returns {string}
 */
export function formatDateRange(
    startISO,
    endISO,
    { calendar = 'gregorian', style = 'short' } = {},
) {
    const start = getDateParts(startISO, calendar);
    const end = getDateParts(endISO, calendar);
    if (!start || !end) return '';

    const name = resolveCalendarName(calendar);

    if (startISO === endISO) {
        return formatDate(startISO, { calendar: name, style });
    }

    if (style === 'numeric') {
        const format = (iso) => formatDate(iso, { calendar: name, style: 'numeric' });
        return `${format(startISO)} – ${format(endISO)}`;
    }

    const monthName = (parts) =>
        style === 'short' && name === 'gregorian' ? parts.monthShort : parts.monthLong;

    const startLabel = `${monthName(start)} ${start.day}`;
    const endLabel = `${monthName(end)} ${end.day}`;

    if (start.year === end.year) {
        return `${startLabel} – ${endLabel}, ${start.year}`;
    }

    return `${startLabel}, ${start.year} – ${endLabel}, ${end.year}`;
}

/**
 * Add days to a Gregorian ISO date.
 *
 * @param {string} iso
 * @param {number} days
 * @returns {string} Gregorian ISO, or `''` for invalid input.
 */
export function addDaysISO(iso, days) {
    const date = parseISODate(iso);
    const amount = Number(days);

    if (!date || !Number.isInteger(amount)) return '';

    return date.add({ days: amount }).toString();
}

/**
 * Start of the week containing `iso`.
 *
 * @param {string} iso
 * @param {number} [weekStart=6] 0 = Sunday ... 6 = Saturday (default: Saturday)
 * @returns {string} Gregorian ISO, or `''` for invalid input.
 */
export function startOfWeekISO(iso, weekStart = 6) {
    const weekday = weekdayOfISO(iso);
    if (weekday === null) return '';

    const start = normalizeWeekStart(weekStart);
    const diff = (weekday - start + 7) % 7;

    return addDaysISO(iso, -diff);
}

/**
 * Inclusive range for a preset, in the selected display calendar.
 *
 * For `month` and `year` the boundary is calendar-aware, e.g. an Aban 1405
 * range starts on Aban 1 and ends on Aban 30, not on Gregorian month edges.
 *
 * @param {'today'|'week'|'month'|'year'} preset
 * @param {{
 *   calendar?: 'gregorian'|'jalali'|'hijri',
 *   weekStart?: number,
 *   today?: string,
 * }} [options]
 * @returns {{ start: string, end: string }}
 */
export function boundsForPreset(
    preset,
    { calendar = 'gregorian', weekStart = 6, today = todayISO() } = {},
) {
    const base = parseISODate(today) ? today : todayISO();
    const name = resolveCalendarName(calendar);

    if (preset === 'today') {
        return { start: base, end: base };
    }

    if (preset === 'week') {
        const start = startOfWeekISO(base, weekStart);
        return { start, end: addDaysISO(start, 6) };
    }

    if (preset === 'month') {
        const parts = getDateParts(base, name);
        const { daysInMonth } = monthGrid(parts.year, parts.month, name, weekStart);
        return {
            start: isoFromCalendarParts(parts.year, parts.month, 1, name),
            end: isoFromCalendarParts(parts.year, parts.month, daysInMonth, name),
        };
    }

    if (preset === 'year') {
        const { year } = getDateParts(base, name);
        const { daysInMonth } = monthGrid(year, 12, name, weekStart);
        return {
            start: isoFromCalendarParts(year, 1, 1, name),
            end: isoFromCalendarParts(year, 12, daysInMonth, name),
        };
    }

    // Unknown presets behave like "today" so callers have a safe fallback.
    return { start: base, end: base };
}

/**
 * Grid metadata for a calendar month.
 *
 * @param {number} year Year in `calendar`
 * @param {number} month 1-12
 * @param {'gregorian'|'jalali'|'hijri'} [calendar='gregorian']
 * @param {number} [weekStart=6] 0 = Sunday ... 6 = Saturday
 * @returns {{ daysInMonth: number, offset: number }} `offset` is 0-6 blank
 *   cells before day 1, relative to `weekStart`. Invalid input returns zeros.
 */
export function monthGrid(year, month, calendar = 'gregorian', weekStart = 6) {
    const y = Number(year);
    const m = Number(month);

    if (!Number.isInteger(y) || !Number.isInteger(m) || m < 1 || m > 12) {
        return { daysInMonth: 0, offset: 0 };
    }

    const name = resolveCalendarName(calendar);
    const cal = CALENDARS[name];
    const firstDay = new CalendarDate(cal, y, m, 1);
    const daysInMonth = cal.getDaysInMonth(firstDay);
    const firstISO = isoFromCalendarParts(y, m, 1, name);
    const offset = firstISO
        ? ((weekdayOfISO(firstISO) - normalizeWeekStart(weekStart) + 7) % 7)
        : 0;

    return { daysInMonth, offset };
}

/**
 * Shift a year/month by a number of months (all three calendars have 12).
 *
 * @param {number} year
 * @param {number} month 1-12
 * @param {number} delta
 * @returns {{ year: number, month: number }|null}
 */
export function shiftMonth(year, month, delta) {
    const y = Number(year);
    const m = Number(month);
    const d = Number(delta);

    if (!Number.isInteger(y) || !Number.isInteger(m) || m < 1 || m > 12 || !Number.isInteger(d)) {
        return null;
    }

    const total = y * 12 + (m - 1) + d;
    const shiftedYear = Math.floor(total / 12);
    const shiftedMonth = ((total % 12) + 12) % 12 + 1;

    return { year: shiftedYear, month: shiftedMonth };
}

/**
 * Convert calendar-specific parts to a Gregorian ISO date.
 *
 * @param {number} year
 * @param {number} month 1-12
 * @param {number} day
 * @param {'gregorian'|'jalali'|'hijri'} [calendar='gregorian']
 * @returns {string|null} Gregorian ISO, or `null` when the parts are invalid.
 */
export function isoFromCalendarParts(year, month, day, calendar = 'gregorian') {
    const y = Number(year);
    const m = Number(month);
    const d = Number(day);

    if (![y, m, d].every(Number.isInteger) || m < 1 || m > 12 || d < 1) return null;

    const name = resolveCalendarName(calendar);
    const cal = CALENDARS[name];
    const candidate = new CalendarDate(cal, y, m, d);

    // Reject silently constrained values (e.g. Jalali 1405-08-31).
    if (candidate.year !== y || candidate.month !== m || candidate.day !== d) return null;
    if (cal.getDaysInMonth(candidate) < d) return null;

    return toCalendar(candidate, GREGORIAN).toString();
}

/**
 * Weekday for a Gregorian ISO date.
 *
 * @param {string} iso
 * @returns {number|null} 0 = Sunday ... 6 = Saturday, or `null` if invalid.
 */
export function weekdayOfISO(iso) {
    const date = parseISODate(iso);
    if (!date) return null;

    return date.toDate('UTC').getUTCDay();
}

/**
 * @param {Date|number|string} value
 * @returns {Date|null}
 */
function toDateObject(value) {
    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    if (typeof value === 'number') {
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? null : date;
    }

    if (typeof value === 'string') {
        // Date-only strings are calendar dates: build from parts, not `new Date`.
        const dateOnly = parseISODate(value);
        if (dateOnly) return dateOnly.toDate('UTC');

        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? null : date;
    }

    return null;
}

/**
 * Format a timestamp as 24-hour `HH:MM` in a timezone.
 *
 * Date-only strings are treated as midnight UTC (the same instant the date
 * arithmetic uses). Returns `''` for invalid input or timezone.
 *
 * @param {Date|number|string} dateLike
 * @param {string} [timeZone='Asia/Tehran']
 * @returns {string}
 */
export function formatTimeInZone(dateLike, timeZone = 'Asia/Tehran') {
    const date = toDateObject(dateLike);
    if (!date) return '';

    try {
        const formatter = new Intl.DateTimeFormat('en-GB', {
            timeZone,
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        });
        const parts = formatter.formatToParts(date);
        const hour = parts.find((part) => part.type === 'hour')?.value ?? '00';
        const minute = parts.find((part) => part.type === 'minute')?.value ?? '00';

        return `${hour.padStart(2, '0')}:${minute.padStart(2, '0')}`;
    } catch {
        return '';
    }
}

/**
 * Gregorian calendar date (`YYYY-MM-DD`) of an instant in a timezone.
 *
 * @param {Date|number|string} dateLike
 * @param {string} [timeZone='Asia/Tehran']
 * @returns {string}
 */
export function dateInTimeZone(dateLike, timeZone = 'Asia/Tehran') {
    const date = toDateObject(dateLike);
    if (!date) return '';

    try {
        const formatter = new Intl.DateTimeFormat('en-US', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        });
        const parts = formatter.formatToParts(date);
        const get = (type) => parts.find((part) => part.type === type)?.value ?? '';
        const year = get('year');
        const month = get('month');
        const day = get('day');

        return year && month && day ? `${year}-${month}-${day}` : '';
    } catch {
        return '';
    }
}

/**
 * Convert a wall-clock date/time in a timezone to a UTC ISO timestamp.
 *
 * Used when a user picks a date with the calendar UI: the backend expects an
 * instant, while the form fields represent Asia/Tehran wall-clock time.
 *
 * @param {string} dateISO `YYYY-MM-DD`
 * @param {string} time `HH:MM`
 * @param {string} [timeZone='Asia/Tehran']
 * @returns {string} UTC ISO string (e.g. `2026-10-24T05:30:00.000Z`), or ''
 */
export function zonedDateTimeToISO(dateISO, time, timeZone = 'Asia/Tehran') {
    if (!parseISODate(dateISO) || !/^\d{2}:\d{2}$/.test(String(time ?? ''))) {
        return '';
    }

    const [year, month, day] = dateISO.split('-').map(Number);
    const [hour, minute] = time.split(':').map(Number);
    if (hour > 23 || minute > 59) return '';

    const target = Date.UTC(year, month - 1, day, hour, minute, 0);
    let timestamp = target;

    try {
        const formatter = new Intl.DateTimeFormat('en-US', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        });

        // Correct the guess by the timezone offset. Two passes cover DST edges.
        for (let attempt = 0; attempt < 3; attempt += 1) {
            const parts = formatter.formatToParts(new Date(timestamp));
            const get = (type) => Number(parts.find((part) => part.type === type)?.value);
            const actual = Date.UTC(
                get('year'),
                get('month') - 1,
                get('day'),
                get('hour'),
                get('minute'),
                0,
            );
            const delta = target - actual;

            if (delta === 0) break;
            timestamp += delta;
        }

        return new Date(timestamp).toISOString();
    } catch {
        return '';
    }
}
