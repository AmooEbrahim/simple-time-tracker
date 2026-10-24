/**
 * Committed holiday data and lookups.
 *
 * Add a country by importing its generated JSON file, adding it to
 * `COUNTRY_DATA`, and adding a matching entry to `HOLIDAY_COUNTRIES`.
 */

import ir from './ir.json' with { type: 'json' };

const EMPTY_DAYS = Object.freeze({});
const COUNTRY_DATA = Object.freeze({ IR: ir });

/** Country selector options. Extend this list when new datasets ship. */
export const HOLIDAY_COUNTRIES = Object.freeze([{ code: 'IR', name: 'Iran' }]);

const setCache = new Map();

function countryKey(country) {
    return typeof country === 'string' ? country.trim().toUpperCase() : '';
}

/**
 * All holiday names keyed by Gregorian ISO date for a country.
 *
 * Unknown or missing countries return an empty object.
 *
 * @param {string} country Country code, e.g. `IR`
 * @returns {Record<string, string[]>}
 */
export function holidayDays(country) {
    return COUNTRY_DATA[countryKey(country)]?.days ?? EMPTY_DAYS;
}

/**
 * Holiday names on one Gregorian ISO date.
 *
 * @param {string} country
 * @param {string} iso
 * @returns {string[]} Empty array when the date is not a holiday.
 */
export function holidayNames(country, iso) {
    if (typeof iso !== 'string') return [];
    return holidayDays(country)[iso] ?? [];
}

/**
 * Cached set of holiday ISO dates for O(1) chart shading/lookups.
 *
 * @param {string} country
 * @returns {Set<string>}
 */
export function holidaySet(country) {
    const key = countryKey(country);
    if (setCache.has(key)) return setCache.get(key);

    const set = new Set(Object.keys(COUNTRY_DATA[key]?.days ?? {}));
    setCache.set(key, set);

    return set;
}

/**
 * @param {string} country
 * @param {string} iso
 * @returns {boolean}
 */
export function isHoliday(country, iso) {
    if (typeof iso !== 'string') return false;
    return holidaySet(country).has(iso);
}
