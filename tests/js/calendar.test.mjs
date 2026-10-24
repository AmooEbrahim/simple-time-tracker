import test from 'node:test';
import assert from 'node:assert/strict';

import {
    CALENDAR_IDS,
    CALENDAR_TYPES,
    MONTHS,
    MONTHS_SHORT,
    WEEKDAYS,
    WEEKDAYS_SHORT,
    addDaysISO,
    boundsForPreset,
    formatDate,
    formatDateRange,
    formatTimeInZone,
    getDateParts,
    isoFromCalendarParts,
    monthGrid,
    parseISODate,
    shiftMonth,
    startOfWeekISO,
    todayISO,
    weekdayOfISO,
} from '../../resources/js/lib/calendar.js';

import {
    HOLIDAY_COUNTRIES,
    holidayDays,
    holidayNames,
    holidaySet,
    isHoliday,
} from '../../resources/js/config/holidays/index.js';

function ymd(iso, calendar) {
    const parts = getDateParts(iso, calendar);
    return parts ? { year: parts.year, month: parts.month, day: parts.day } : null;
}

test('exports the supported calendars and English label maps', () => {
    assert.deepEqual(CALENDAR_TYPES, ['gregorian', 'jalali', 'hijri']);
    assert.deepEqual(CALENDAR_IDS, {
        gregorian: 'gregory',
        jalali: 'persian',
        hijri: 'islamic-umalqura',
    });

    assert.equal(MONTHS.jalali.length, 12);
    assert.equal(MONTHS.hijri.length, 12);
    assert.equal(MONTHS.gregorian.length, 12);
    assert.equal(MONTHS_SHORT.jalali.length, 12);
    assert.equal(MONTHS_SHORT.hijri.length, 12);
    assert.equal(WEEKDAYS.length, 7);
    assert.equal(WEEKDAYS_SHORT.length, 7);
    assert.equal(WEEKDAYS[0], 'Sunday');
    assert.equal(WEEKDAYS[6], 'Saturday');
    assert.equal(MONTHS.jalali[7], 'Aban');
    assert.equal(MONTHS.hijri[4], 'Jumada al-Awwal');
    assert.equal(MONTHS_SHORT.jalali[7], 'Aba');
    assert.equal(MONTHS_SHORT.hijri[5], 'Jum II');
});

test('converts Gregorian ISO dates to Jalali', () => {
    assert.deepEqual(ymd('2026-03-21', 'jalali'), { year: 1405, month: 1, day: 1 });
    assert.deepEqual(ymd('2026-10-24', 'jalali'), { year: 1405, month: 8, day: 2 });

    assert.equal(formatDate('2026-03-21', { calendar: 'jalali' }), 'Farvardin 1, 1405');
    assert.equal(formatDate('2026-10-24', { calendar: 'jalali' }), 'Aban 2, 1405');
});

test('converts Gregorian ISO dates to Umm al-Qura Hijri', () => {
    assert.deepEqual(ymd('2026-10-24', 'hijri'), { year: 1448, month: 5, day: 13 });
    assert.equal(formatDate('2026-10-24', { calendar: 'hijri', style: 'numeric' }), '1448/05/13');
    assert.equal(
        formatDate('2026-10-24', { calendar: 'hijri' }),
        'Jumada al-Awwal 13, 1448',
    );
});

test('formats Gregorian dates in long, short, numeric, and no-year styles', () => {
    assert.equal(formatDate('2026-10-24'), 'October 24, 2026');
    assert.equal(formatDate('2026-10-24', { weekday: true }), 'Saturday, October 24, 2026');
    assert.equal(formatDate('2026-10-24', { style: 'short' }), 'Oct 24, 2026');
    assert.equal(
        formatDate('2026-10-24', { style: 'short', weekday: true }),
        'Sat, Oct 24, 2026',
    );
    assert.equal(formatDate('2026-10-24', { style: 'numeric' }), '2026-10-24');
    assert.equal(formatDate('2026-10-24', { year: false }), 'October 24');
    assert.equal(formatDate('2026-10-24', { calendar: 'jalali', year: false }), 'Aban 2');
    assert.equal(formatDate('2026-10-24', { calendar: 'jalali', style: 'numeric' }), '1405/08/02');
});

test('formats ranges across months and years', () => {
    assert.equal(
        formatDateRange('2026-10-24', '2026-10-30', { calendar: 'jalali' }),
        'Aban 2 – Aban 8, 1405',
    );
    assert.equal(
        formatDateRange('2026-11-21', '2026-11-27', { calendar: 'jalali' }),
        'Aban 30 – Azar 6, 1405',
    );
    assert.equal(
        formatDateRange('2026-03-20', '2026-03-22', { calendar: 'jalali' }),
        'Esfand 29, 1404 – Farvardin 2, 1405',
    );
    assert.equal(
        formatDateRange('2026-10-24', '2026-10-30', { style: 'short' }),
        'Oct 24 – Oct 30, 2026',
    );
    assert.equal(
        formatDateRange('2026-10-24', '2026-10-24', { calendar: 'jalali' }),
        'Aban 2, 1405',
    );
});

test('calculates week starts for Saturday and Sunday', () => {
    assert.equal(weekdayOfISO('2026-10-24'), 6);

    assert.equal(startOfWeekISO('2026-10-24', 6), '2026-10-24');
    assert.equal(startOfWeekISO('2026-10-25', 6), '2026-10-24');
    assert.equal(startOfWeekISO('2026-10-24', 0), '2026-10-18');
    assert.equal(startOfWeekISO('2026-10-24', 1), '2026-10-19');
});

test('calculates calendar-aware month and year bounds', () => {
    assert.deepEqual(
        boundsForPreset('month', { calendar: 'jalali', today: '2026-10-24' }),
        { start: '2026-10-23', end: '2026-11-21' },
    );

    assert.deepEqual(
        boundsForPreset('month', { calendar: 'hijri', today: '2026-10-24' }),
        { start: '2026-10-12', end: '2026-11-10' },
    );

    assert.deepEqual(
        boundsForPreset('year', { calendar: 'jalali', today: '2026-10-24' }),
        { start: '2026-03-21', end: '2027-03-20' },
    );

    assert.deepEqual(
        boundsForPreset('year', { calendar: 'hijri', today: '2026-10-24' }),
        { start: '2026-06-16', end: '2027-06-05' },
    );

    assert.deepEqual(
        boundsForPreset('week', { weekStart: 6, today: '2026-10-24' }),
        { start: '2026-10-24', end: '2026-10-30' },
    );

    assert.deepEqual(
        boundsForPreset('today', { today: '2026-10-24' }),
        { start: '2026-10-24', end: '2026-10-24' },
    );
});

test('round-trips Hijri month bounds and grid metadata', () => {
    const grid = monthGrid(1448, 5, 'hijri', 6);

    assert.equal(grid.daysInMonth, 30);
    assert.equal(grid.offset, 2);
    assert.ok(grid.offset >= 0 && grid.offset <= 6);

    const first = isoFromCalendarParts(1448, 5, 1, 'hijri');
    const last = isoFromCalendarParts(1448, 5, grid.daysInMonth, 'hijri');

    assert.equal(first, '2026-10-12');
    assert.equal(last, '2026-11-10');
    assert.deepEqual(ymd(first, 'hijri'), { year: 1448, month: 5, day: 1 });
    assert.deepEqual(ymd(last, 'hijri'), { year: 1448, month: 5, day: grid.daysInMonth });
});

test('shifts months across year boundaries', () => {
    assert.deepEqual(shiftMonth(1405, 12, 1), { year: 1406, month: 1 });
    assert.deepEqual(shiftMonth(1405, 1, -1), { year: 1404, month: 12 });
    assert.deepEqual(shiftMonth(2026, 1, -1), { year: 2025, month: 12 });
    assert.equal(shiftMonth(2026, 13, 1), null);
});

test('adds days and start-of-week safely in UTC', () => {
    assert.equal(addDaysISO('2026-03-21', 1), '2026-03-22');
    assert.equal(addDaysISO('2026-03-01', -1), '2026-02-28');
    assert.equal(addDaysISO('2024-02-28', 1), '2024-02-29');
    assert.equal(addDaysISO('bad-date', 1), '');
});

test('returns null/empty results for invalid dates instead of throwing', () => {
    assert.equal(parseISODate('2026-02-30'), null);
    assert.equal(parseISODate('2026-13-01'), null);
    assert.equal(parseISODate('2026-2-03'), null);
    assert.equal(parseISODate(''), null);
    assert.equal(parseISODate(null), null);

    assert.equal(getDateParts('2026-02-30'), null);
    assert.equal(formatDate('not-a-date'), '');
    assert.equal(formatDateRange('2026-10-24', 'nope'), '');
    assert.equal(formatTimeInZone('not-a-date'), '');

    assert.equal(isoFromCalendarParts(2026, 2, 30, 'gregorian'), null);
    assert.equal(isoFromCalendarParts(1405, 8, 31, 'jalali'), null);
    assert.equal(isoFromCalendarParts(1405, 13, 1, 'jalali'), null);
    assert.equal(weekdayOfISO('2026-13-01'), null);

    assert.deepEqual(monthGrid(2026, 13), { daysInMonth: 0, offset: 0 });
});

test('formats time in a timezone with 24-hour Latin digits', () => {
    assert.equal(formatTimeInZone('2026-10-24T12:34:00Z', 'Asia/Tehran'), '16:04');
    assert.equal(formatTimeInZone('2026-10-24T12:34:00Z', 'UTC'), '12:34');
    assert.match(todayISO(), /^\d{4}-\d{2}-\d{2}$/);
});

test('loads holiday data and handles overrides', () => {
    assert.deepEqual(HOLIDAY_COUNTRIES, [{ code: 'IR', name: 'Iran' }]);

    assert.ok(holidayNames('IR', '2026-03-21').includes('Nowruz'));
    assert.ok(isHoliday('IR', '2026-03-21'));
    assert.deepEqual(holidayNames('IR', '2026-03-01'), ['Public Mourning']);
    assert.ok(isHoliday('IR', '2026-03-07'));

    const dates = Object.keys(holidayDays('IR'));
    assert.ok(dates.length > 100);
    assert.deepEqual(dates, [...dates].sort());

    assert.strictEqual(holidaySet('IR'), holidaySet('IR'));
    assert.ok(holidaySet('IR').has('2026-03-21'));
});

test('returns empty holiday results for unknown countries', () => {
    assert.deepEqual(holidayDays('ZZ'), {});
    assert.deepEqual(holidayNames('ZZ', '2026-03-21'), []);
    assert.equal(isHoliday('ZZ', '2026-03-21'), false);
    assert.equal(holidaySet('ZZ').size, 0);
    assert.deepEqual(holidayNames(undefined, '2026-03-21'), []);
});
