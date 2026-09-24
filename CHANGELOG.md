# Changelog

## 2.0.0 (2026-09-25)

Silverstripe 5 and 6. Silverstripe 4 is dropped, which is why this is a major release. See
[UPGRADING.md](UPGRADING.md).

### Breaking

- **Silverstripe 4 is no longer supported.** `silverstripe/framework` is now `^5 || ^6` (was
  `^4 || ^5`), PHP `^8.1` (was not declared), and `silverstripe/vendor-plugin` `^2 || ^3` is now
  required (the `client/` expose depends on it; it was not declared). Silverstripe 4 projects keep
  resolving the `1.x` tags.
- **`validateLatLong()` is stricter.** Both parts must be numeric, latitude within -90..90 and
  longitude within -180..180. `"52abc,4"` and `"999,999"` used to pass and no longer do.
- **`calCulateDistance()` returns `null`** when either coordinate is not a valid `"lat,long"`
  string, instead of throwing a `TypeError`. Values that 1.x parsed only partly (trailing text, a
  third part, a decimal comma, a float instead of a string) also return `null` now, where they
  used to give a distance with a PHP warning.

### Fixed

- A field without address input fields threw a `TypeError` on PHP 8 as soon as it rendered
  (`getAddressInputFields()` counted an undefined variable).
- On Silverstripe 6, `data-addressfields` and `data-locationpickeroptions` rendered empty, so the
  address lookup from other fields and the map options were silently lost. Silverstripe 6 casts a
  list array to an `ArrayList` and an associative one to an `ArrayData`, and neither has `JSON()`;
  the JSON is now built in PHP
  (`getAddressInputFieldsJSON()`, `getLocationPickerOptionsJSON()`) and renders the same on 5 and 6.
- `validateLatLong()` rejected any coordinate on the equator or the prime meridian (`"0,5.1"`).
- `calCulateDistance()` threw a `TypeError` on a malformed coordinate. Projects that pass a value
  from a visitor's cookie got a server error from a bad cookie.
- `Field()` discarded the properties passed to it.
- On Silverstripe 6 the search and clear buttons used Bootstrap 4 input-group markup
  (`input-group-prepend`, `input-group-append`, `font-weight-bold`), which the Bootstrap 5 CMS has
  no styles for. The template now renders flat Bootstrap 5 markup (`fw-bold`) on Silverstripe 6 and
  keeps the Bootstrap 4 markup on Silverstripe 5 (`getUsesBootstrap4InputGroup()`).

### Added

- A behavioural test suite (`tests/`), run in CI against Silverstripe 5 and 6: the field's markup
  and data attributes (the contract with the scripts), getters and setters, requirements and
  API-key selection, the template global, the static helpers, the exposed resources, and a real
  CMS page edit form.
- README: requirements, a version compatibility table, usage of address fields and map options,
  the defaults the field sets, the static helpers, the assets it loads, and how to run the tests.
- `license` (MIT, as in `LICENSE`), `funding` and `autoload-dev` in `composer.json`.

### Issues

- No issues were open (or had ever been filed) at the time of this release.

## 1.0.7 (2026-09-25)

Bug-fix release on the `v1` line (Silverstripe 4 and 5).

- `calCulateDistance()` threw a `TypeError` on a malformed coordinate (for example a value from a
  visitor's cookie); it now returns `null`. Values that 1.0.6 parsed only partly (trailing text, a
  third part, a decimal comma, a float instead of a string) also return `null` now, where they used
  to give a distance with a PHP warning.
- A field without address input fields threw a `TypeError` on PHP 8 as soon as it rendered.

## 1.0.6

- Silverstripe 5 support (`silverstripe/framework` `^4 || ^5`).

## 1.0.5

- Global `$GMapsApiKey` template variable.

## 1.0.0 - 1.0.4

- Silverstripe 4 vendor module; the Google Maps key moved to the environment.
