import { test as base, expect, type Locator, type Page, type Request } from '@playwright/test';

// Shared fixtures and helpers for the latlongfield specs.
//
// The CMS screen is the fixture ModelAdmin in tests/browser/fixtures/ (copied into the scratch host
// by the runner): a place's edit form under /admin/ll-browser/places, holding the LatLongField GPS.
// Every spec first asks the fixture reset endpoint for a fresh record of its own (LlBPlace::reseed).
//
// The Google Maps JavaScript API is NOT loaded from Google: the field's script tag is answered by a
// small stand-in (GOOGLE_MAPS_STUB below), so the specs need no API key, no network and no billing,
// and can see exactly what the module asks of the API (geocoder requests, map options, marker
// positions). What the stand-in cannot show is how Google's real map renders.

/** What the stand-in recorded, read back with gm(page). */
export type GmState = {
    geocodes: string[];
    maps: { options: { zoom: number; mapTypeId: string; center: [number, number] }; zoom: number; center: [number, number]; calls: string[] }[];
    markers: { position: [number, number]; draggable: boolean }[];
};

/**
 * The stand-in for maps.google.com/maps/api/js: the parts of google.maps that
 * client/js/jquery.locationpicker.js uses (Geocoder, LatLng, Map, Marker, MapTypeId, GeocoderStatus,
 * event), recording every call in window.__gm. A test sets window.__gm.answer to decide what the next
 * geocode returns, and calls window.__gm.fire(target, eventName, latLng) to play a user action on
 * the map (double-click) or the marker (end of a drag).
 */
const GOOGLE_MAPS_STUB = `
(function () {
    var gm = window.__gm = { geocodes: [], maps: [], markers: [], listeners: [], answer: { status: 'ZERO_RESULTS', results: [] } };
    function LatLng(lat, lng) { this._lat = Number(lat); this._lng = Number(lng); }
    LatLng.prototype.lat = function () { return this._lat; };
    LatLng.prototype.lng = function () { return this._lng; };
    var pair = function (ll) { return ll ? [ll.lat(), ll.lng()] : null; };
    function Map(div, options) {
        this.div = div; this.options = options; this.zoom = options.zoom; this.center = options.center; this.calls = [];
        div.innerHTML = '<div class="gm-stub-map">map</div>';
        gm.maps.push(this);
    }
    Map.prototype.fitBounds = function () { this.calls.push('fitBounds'); };
    Map.prototype.getZoom = function () { return this.zoom; };
    Map.prototype.setZoom = function (z) { this.zoom = z; this.calls.push('setZoom ' + z); };
    Map.prototype.panTo = function (ll) { this.center = ll; this.calls.push('panTo'); };
    Map.prototype.setCenter = function (ll) { this.center = ll; this.calls.push('setCenter'); };
    function Marker(options) { this.options = options; this.position = options.position; gm.markers.push(this); }
    Marker.prototype.setPosition = function (ll) { this.position = ll; };
    function Geocoder() {}
    Geocoder.prototype.geocode = function (request, callback) {
        gm.geocodes.push(request.address);
        var answer = gm.answer;
        setTimeout(function () {
            callback(answer.results.map(function (r) {
                return { geometry: { location: new LatLng(r.lat, r.lng), viewport: { stub: true } } };
            }), answer.status);
        }, 10);
    };
    var event = {
        addListener: function (target, name, fn) { gm.listeners.push({ target: target, name: name, fn: fn }); },
        trigger: function (target, name, arg) {
            gm.listeners.forEach(function (l) { if (l.target === target && l.name === name) l.fn(arg); });
        }
    };
    gm.fire = function (kind, name, lat, lng) {
        var target = kind === 'map' ? gm.maps[0] : gm.markers[0];
        if (kind === 'marker' && name === 'dragend') target.position = new LatLng(lat, lng);
        event.trigger(target, name, { latLng: new LatLng(lat, lng) });
    };
    gm.snapshot = function () {
        return {
            geocodes: gm.geocodes.slice(),
            maps: gm.maps.map(function (m) {
                return { options: { zoom: m.options.zoom, mapTypeId: m.options.mapTypeId, center: pair(m.options.center) }, zoom: m.zoom, center: pair(m.center), calls: m.calls.slice() };
            }),
            markers: gm.markers.map(function (m) { return { position: pair(m.position), draggable: !!m.options.draggable }; })
        };
    };
    window.google = { maps: {
        LatLng: LatLng, Map: Map, Marker: Marker, Geocoder: Geocoder, event: event,
        MapTypeId: { ROADMAP: 'roadmap', SATELLITE: 'satellite', HYBRID: 'hybrid', TERRAIN: 'terrain' },
        GeocoderStatus: { OK: 'OK', ZERO_RESULTS: 'ZERO_RESULTS' }
    } };
})();
`;

/** Messages of alert() dialogs per page, in order; a spec that expects one takes it with nextAlert(). */
const alerts = new WeakMap<Page, string[]>();

/**
 * test, extended with two automatic fixtures:
 * - the Google Maps stand-in: every request for the Maps JavaScript API is answered with
 *   GOOGLE_MAPS_STUB, and the URLs asked for are kept (mapsRequests) for specs to check;
 * - a guard: every spec fails if the page logs a console error, throws an uncaught exception, or
 *   shows an alert() the spec did not take with nextAlert(). The picker reports a failed lookup
 *   with alert(), so an unexpected one is a failure. Warnings do not count.
 */
export const test = base.extend<{ mapsRequests: string[]; guard: void }>({
    mapsRequests: [
        async ({ page }, use) => {
            const seen: string[] = [];
            await page.route(/\/\/maps\.google(apis)?\.com\/maps\/api\/js/, (route) => {
                seen.push(route.request().url());
                return route.fulfill({ status: 200, contentType: 'text/javascript', body: GOOGLE_MAPS_STUB });
            });
            await use(seen);
        },
        { auto: true },
    ],
    guard: [
        async ({ page }, use, testInfo) => {
            const errors: string[] = [];
            alerts.set(page, []);
            page.on('console', (msg) => {
                if (msg.type() === 'error') {
                    errors.push(`console.error: ${msg.text()} (${msg.location().url})`);
                }
            });
            page.on('pageerror', (err) => errors.push(`uncaught: ${err.message}`));
            page.on('dialog', async (dialog) => {
                if (dialog.type() === 'alert') {
                    alerts.get(page)!.push(dialog.message());
                } else if (dialog.type() !== 'beforeunload') {
                    errors.push(`unexpected ${dialog.type()}(): ${dialog.message()}`);
                }
                await (dialog.type() === 'beforeunload' ? dialog.accept() : dialog.dismiss());
            });

            await use();

            const leftover = alerts.get(page)!.map((m) => `unexpected alert(): ${m}`);
            errors.push(...leftover);
            if (errors.length) {
                await testInfo.attach('console-errors', { body: errors.join('\n'), contentType: 'text/plain' });
            }
            expect(errors, 'no console errors, uncaught exceptions or unexpected alerts').toEqual([]);
        },
        { auto: true },
    ],
});

export { expect };

/** Wait for the next alert() and return its message (and mark it as expected). */
export async function nextAlert(page: Page): Promise<string> {
    await expect.poll(() => alerts.get(page)!.length, { message: 'an alert() was shown' }).toBeGreaterThan(0);
    return alerts.get(page)!.shift()!;
}

/** What the Google Maps stand-in has recorded so far. */
export async function gm(page: Page): Promise<GmState> {
    return page.evaluate(() => (window as any).__gm.snapshot());
}

/** Decide what the stand-in geocoder answers next: a location, or a failure status. */
export async function answerGeocode(page: Page, answer: { lat: number; lng: number } | string): Promise<void> {
    await page.evaluate((a) => {
        (window as any).__gm.answer = typeof a === 'string' ? { status: a, results: [] } : { status: 'OK', results: [a] };
    }, answer);
}

/** Play a map double-click or the end of a marker drag at a position, as Google would report it. */
export async function fire(page: Page, kind: 'map' | 'marker', name: 'dblclick' | 'dragend', lat: number, lng: number): Promise<void> {
    await page.evaluate(([k, n, la, ln]) => (window as any).__gm.fire(k, n, la, ln), [kind, name, lat, lng] as const);
}

/** Ask the fixture reset endpoint for a fresh place; returns its ID. */
export async function reseed(page: Page, title: string, setup: 'plain' | 'address', gps = ''): Promise<number> {
    const response = await page.request.get('/admin/ll-reset/reseed', { params: { title, setup, gps } });
    expect(response.status(), `reseed "${title}"`).toBe(200);
    return (await response.json()).id;
}

export function editUrl(id: number): string {
    return `/admin/ll-browser/places/EditForm/field/places/item/${id}/edit`;
}

/** The field's parts in the edit form. */
export function field(page: Page) {
    const input = page.locator('#Form_ItemEditForm_GPS');
    const group = page.locator('.latlong-fieldgroup');
    return {
        input,
        group,
        search: group.locator('.btn-latlong-search'),
        clear: group.locator('.btn-latlong-clear'),
        // Created by the picker script next to the search button; hidden until the map opens.
        picker: page.locator('#Form_ItemEditForm_GPS-picker'),
    };
}

/**
 * Open a place's edit form with a full page load and wait until the module's script has run: the
 * picker container exists only once latlongfield.js matched the input and set up the picker.
 */
export async function openPlace(page: Page, id: number) {
    await page.goto(editUrl(id));
    const f = field(page);
    await expect(f.input).toBeVisible();
    await expect(f.picker).toHaveCount(1);
    return f;
}

/** Seed a place and open it: the start of most specs. */
export async function freshPlace(page: Page, title: string, setup: 'plain' | 'address', gps = '') {
    const id = await reseed(page, title, setup, gps);
    return { id, ...(await openPlace(page, id)) };
}

/** Save the edit form with its Save button and wait for the CMS's AJAX save to come back 200. */
export async function saveForm(page: Page): Promise<Request> {
    const posted = page.waitForRequest((r) => r.method() === 'POST' && /\/ItemEditForm(\?|$)/.test(r.url()));
    await page.locator('button[name="action_doSave"]').click();
    const request = await posted;
    expect(['xhr', 'fetch']).toContain(request.resourceType());
    expect((await request.response())?.status()).toBe(200);
    return request;
}

/** Whether a locator is shown (the picker fades in and out with jQuery). */
export async function isShown(locator: Locator): Promise<boolean> {
    return locator.evaluate((el) => getComputedStyle(el).display !== 'none' && (el as HTMLElement).offsetHeight > 0);
}
