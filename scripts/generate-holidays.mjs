#!/usr/bin/env node
/**
 * Generate the committed Iran holiday dataset.
 *
 * Official days come from @jalali-js/holidays (University of Tehran Calendar
 * Centre data). Ad-hoc public mourning days that only lived in the old
 * `resources/js/config/offDays.js` live in `overrides.json` and are merged in
 * here. The output is deterministic: dates are sorted, names are deduplicated,
 * and the `updated` value is fixed rather than using the build date.
 *
 * Usage: npm run holidays:generate
 */

import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { holidaysInYear } from '@jalali-js/holidays';
import { isoFromCalendarParts } from '../resources/js/lib/calendar.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(__dirname, '..');
const outputDir = path.join(rootDir, 'resources/js/config/holidays');
const overridesPath = path.join(outputDir, 'overrides.json');
const outputPath = path.join(outputDir, 'ir.json');

const FIRST_JALALI_YEAR = 1402;
const LAST_JALALI_YEAR = 1406;
const UPDATED = '2026-10-24';

/** @type {Map<string, string[]>} */
const days = new Map();

function addHoliday(iso, names) {
    if (!Array.isArray(names) || names.length === 0) return;

    if (!days.has(iso)) {
        days.set(iso, []);
    }

    const list = days.get(iso);
    for (const name of names) {
        if (typeof name !== 'string' || name.length === 0) continue;
        if (!list.includes(name)) list.push(name);
    }
}

for (let year = FIRST_JALALI_YEAR; year <= LAST_JALALI_YEAR; year += 1) {
    for (const holiday of holidaysInYear(year)) {
        const iso = isoFromCalendarParts(
            holiday.year ?? year,
            holiday.month,
            holiday.day,
            'jalali',
        );

        if (!iso) {
            throw new Error(
                `Could not convert Jalali holiday ${year}/${holiday.month}/${holiday.day} (${holiday.id})`,
            );
        }

        addHoliday(iso, [holiday.names.en]);
    }
}

const overrides = JSON.parse(await readFile(overridesPath, 'utf8'));
for (const [iso, names] of Object.entries(overrides.days ?? {})) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
        throw new Error(`Invalid override date: ${iso}`);
    }

    addHoliday(iso, names);
}

const sortedDays = {};
for (const iso of [...days.keys()].sort()) {
    sortedDays[iso] = days.get(iso);
}

const output = {
    country: 'IR',
    source:
        'University of Tehran Calendar Centre via @jalali-js/holidays 0.4.2; ad-hoc closures from prior app data',
    updated: UPDATED,
    days: sortedDays,
};

await mkdir(outputDir, { recursive: true });
await writeFile(outputPath, `${JSON.stringify(output, null, 2)}\n`, 'utf8');

console.log(
    `Wrote ${Object.keys(sortedDays).length} holiday dates (Jalali ${FIRST_JALALI_YEAR}-${LAST_JALALI_YEAR}) to ${path.relative(rootDir, outputPath)}`,
);
