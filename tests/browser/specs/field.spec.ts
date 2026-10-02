import { test, expect, answerGeocode, fire, freshPlace, gm, isShown, nextAlert, openPlace, saveForm } from './support';

// The LatLongField in a CMS edit form: the markup the scripts rely on, the map picker (with the
// Google Maps stand-in from support.ts), and that what the picker writes into the input is what
// the form saves. 'plain' places have the field on its own; 'address' places look the address up
// from the StreetAddress and City fields and pass map options (zoom 12, SATELLITE).

const ROTTERDAM: [number, number] = [51.92556, 4.47646]; // the picker's default centre

test('renders the input between search and clear buttons, in the markup of the CMS Bootstrap', async ({ page, mapsRequests }, testInfo) => {
    const f = await freshPlace(page, 'Render', 'plain', '52.16502,4.48318');

    await expect(f.input).toHaveValue('52.16502,4.48318');
    await expect(f.input).toHaveClass(/\blatlong\b/);
    await expect(f.input).toHaveClass(/\btext\b/);
    await expect(f.input).toHaveAttribute('placeholder', '(empty / no location yet)');
    await expect(f.input).not.toHaveAttribute('readonly', /.*/);
    await expect(page.locator('#describes-Form_ItemEditForm_GPS')).toContainText('click “🔍” (search)');
    await expect(f.search).toHaveAttribute('title', 'Search');
    await expect(f.clear).toHaveAttribute('title', 'Clear');

    // Bootstrap 4 wrappers for the SS5 CMS, flat Bootstrap 5 markup for the SS6 CMS (2.0.0).
    if (testInfo.project.name === 'ss5') {
        await expect(f.group.locator('> .input-group-prepend > .btn-latlong-search')).toHaveCount(1);
        await expect(f.group.locator('> .input-group-append > .btn-latlong-clear')).toHaveClass(/font-weight-bold/);
    } else {
        await expect(f.group.locator('.input-group-prepend, .input-group-append')).toHaveCount(0);
        await expect(f.group.locator('> .btn-latlong-search')).toHaveCount(1);
        await expect(f.group.locator('> .btn-latlong-clear')).toHaveClass(/\bfw-bold\b/);
    }

    // client/css/latlongfield.css is exposed and loaded.
    expect(await f.group.evaluate((el) => getComputedStyle(el).maxWidth)).toBe('440px');

    // The Maps API was asked for once (no key is configured on the test host), and the picker has
    // built its map at the stored position with the defaults, closed until it is needed.
    expect(mapsRequests).toHaveLength(1);
    expect(mapsRequests[0]).toMatch(/\/\/maps\.google\.com\/maps\/api\/js\?key=$/);
    expect(await isShown(f.picker)).toBe(false);
    const state = await gm(page);
    expect(state.maps).toHaveLength(1);
    expect(state.maps[0].options).toEqual({ zoom: 15, mapTypeId: 'roadmap', center: [52.16502, 4.48318] });
    expect(state.markers).toEqual([{ position: [52.16502, 4.48318], draggable: true }]);
});

test('an empty field opens the map at the default centre', async ({ page }) => {
    await freshPlace(page, 'Empty', 'plain');
    const state = await gm(page);
    expect(state.maps[0].options.center).toEqual(ROTTERDAM);
    expect(state.markers[0].position).toEqual(ROTTERDAM);
});

test('search geocodes the typed address, opens the map and writes the coordinates, which Save stores', async ({ page }) => {
    const { id, ...f } = await freshPlace(page, 'Search', 'plain');
    await answerGeocode(page, { lat: 51.5154321987, lng: -0.1415432198 });

    await f.input.fill('49 Oxford Street, London');
    await f.search.click();

    // Rounded to six decimals; the map fits the result's viewport and zooms in two steps.
    await expect(f.input).toHaveValue('51.515432,-0.141543');
    await expect.poll(() => isShown(f.picker)).toBe(true);
    const state = await gm(page);
    expect(state.geocodes).toEqual(['49 Oxford Street, London']);
    expect(state.markers[0].position).toEqual([51.515432, -0.141543]);
    expect(state.maps[0].calls.slice(0, 2)).toEqual(['fitBounds', 'setZoom 17']);

    await saveForm(page);
    const reloaded = await openPlace(page, id);
    await expect(reloaded.input).toHaveValue('51.515432,-0.141543');
});

test('Enter in the field searches too', async ({ page }) => {
    const f = await freshPlace(page, 'Enter', 'plain');
    await answerGeocode(page, { lat: 48.8583, lng: 2.2945 });
    await f.input.fill('Eiffel Tower');
    await f.input.press('Enter');
    await expect(f.input).toHaveValue('48.8583,2.2945');
    expect((await gm(page)).geocodes).toEqual(['Eiffel Tower']);
});

test('search on an empty field asks for an address and looks nothing up', async ({ page }) => {
    const f = await freshPlace(page, 'Nothing', 'plain');
    await f.search.click();
    expect(await nextAlert(page)).toBe('Please enter an address or Lat/Lng position.');
    expect((await gm(page)).geocodes).toEqual([]);
    await expect(f.input).toHaveValue('');
});

test('an address Google cannot find is reported and leaves the field as typed', async ({ page }) => {
    const f = await freshPlace(page, 'Unknown', 'plain');
    await answerGeocode(page, 'ZERO_RESULTS');
    await f.input.fill('Nowhere at all');
    await f.search.click();
    expect(await nextAlert(page)).toBe('Geocode was not successful for the following reason: ZERO_RESULTS');
    await expect(f.input).toHaveValue('Nowhere at all');
    expect(await isShown(f.picker)).toBe(false);
});

test('typed coordinates are shown on the map without a lookup', async ({ page }) => {
    const f = await freshPlace(page, 'Coordinates', 'plain');
    await f.input.fill('40.689247,-74.044502');
    await f.search.click();
    await expect.poll(() => isShown(f.picker)).toBe(true);
    const state = await gm(page);
    expect(state.geocodes).toEqual([]);
    expect(state.markers[0].position).toEqual([40.689247, -74.044502]);
    await expect(f.input).toHaveValue('40.689247,-74.044502');
});

test('focusing a field that holds coordinates opens the map; moving to another field closes it', async ({ page }) => {
    const f = await freshPlace(page, 'Focus', 'plain', '52.16502,4.48318');
    await f.input.focus();
    await expect.poll(() => isShown(f.picker)).toBe(true);
    // A click on the map itself keeps it open.
    await f.picker.click();
    await page.waitForTimeout(300);
    expect(await isShown(f.picker)).toBe(true);
    await page.locator('input[name="Title"]').click();
    await expect.poll(() => isShown(f.picker)).toBe(false);
});

test('dragging the marker or double-clicking the map moves the position', async ({ page }) => {
    const { id, ...f } = await freshPlace(page, 'Drag', 'plain', '52.16502,4.48318');
    await f.input.focus();
    await expect.poll(() => isShown(f.picker)).toBe(true);

    await fire(page, 'marker', 'dragend', 52.1601234567, 4.4970987654);
    await expect(f.input).toHaveValue('52.160123,4.497099');

    await fire(page, 'map', 'dblclick', 52.2, 4.5);
    await expect(f.input).toHaveValue('52.2,4.5');
    expect((await gm(page)).markers[0].position).toEqual([52.2, 4.5]);

    await saveForm(page);
    await expect((await openPlace(page, id)).input).toHaveValue('52.2,4.5');
});

test('the clear button empties the field, and Save stores it empty', async ({ page }) => {
    const { id, ...f } = await freshPlace(page, 'Clear', 'plain', '52.16502,4.48318');
    await f.clear.click();
    await expect(f.input).toHaveValue('');
    await saveForm(page);
    await expect((await openPlace(page, id)).input).toHaveValue('');
});

test('with address fields the input is read-only and search geocodes those fields; map options apply', async ({ page }) => {
    const { id, ...f } = await freshPlace(page, 'Address', 'address');
    await expect(f.input).toHaveAttribute('readonly', 'readonly');

    // setLocationPickerOptions() reaches the picker (on SS6 this attribute used to render empty).
    expect((await gm(page)).maps[0].options).toEqual({ zoom: 12, mapTypeId: 'satellite', center: ROTTERDAM });

    // The lookup uses the address fields as they are in the form now, joined with ", ".
    await page.locator('input[name="City"]').fill('Den Haag');
    await answerGeocode(page, { lat: 52.0799838, lng: 4.3113461 });
    await f.search.click();
    await expect(f.input).toHaveValue('52.079984,4.311346');
    expect((await gm(page)).geocodes).toEqual(['Breestraat 1, Den Haag']);

    await saveForm(page);
    const reloaded = await openPlace(page, id);
    await expect(reloaded.input).toHaveValue('52.079984,4.311346');
    await expect(page.locator('input[name="City"]')).toHaveValue('Den Haag');
});
