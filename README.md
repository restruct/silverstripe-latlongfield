Simple Silverstripe Latlong Field
=================================

*Maintained by [Restruct](https://github.com/restruct). If this module saves you time, you can
[support ongoing maintenance](https://github.com/sponsors/restruct).*

<img width="465" alt="Screenshot 2021-02-27 at 16 22 59" src="https://user-images.githubusercontent.com/1005986/109391592-26ad4e00-7918-11eb-89ee-c68f441f4549.png">
CMS editor can type street address, then click search. A (draggable) pointer will be shown on a map and the field's content will be replaced with the LatLong code. Originally abstracted from the mappable module (I think).

The value is stored as a plain `"lat,long"` string (eg. `52.16502,4.48318`), so any text column
(`Varchar`) will do. The class also carries a few static helpers for such strings: validation,
distance between two coordinates, and server-side geocoding.

## Requirements

* Silverstripe 5 or 6 (`silverstripe/framework`)
* PHP 8.1 or newer
* A Google Maps API key (see [Config](#config))
* The field's script is written for the CMS: it needs jQuery and entwine, which the CMS admin
  provides. On a front-end form you have to provide both yourself.

## Installation

```
composer require restruct/silverstripe-latlongfield
```

## Version compatibility

| Branch | Module version | Silverstripe | PHP |
|--------|----------------|--------------|-----|
| `master` | `2.x` | `^5 \|\| ^6` | `^8.1` |
| (tags only) | `1.0.6` | `^4 \|\| ^5` | not declared |
| (tags only) | `1.0.0` - `1.0.5` | 4 (not declared) | not declared |

`composer.json` is the source of truth; this table is a convenience copy. Silverstripe 4 reached
end of life in April 2025 and is no longer supported or tested here. Projects still on it should
stay on the `1.x` tags, which remain available. Upgrading from 1.x: see [UPGRADING.md](UPGRADING.md).

## Config

```
.env:
GMAPS_API_KEY="..."
GMAPS_BROWSER_KEY="..." (optional secondary 'public' key to use in the browser)
```

* `GMAPS_API_KEY` is used for server-side calls (`LatLongField::GeoCode()`), and in the browser
  when there is no `GMAPS_BROWSER_KEY`.
* `GMAPS_BROWSER_KEY`, when set, is used for everything that reaches the browser (the Maps
  JavaScript API and the `$GMapsApiKey` template variable), so the server key never has to be
  published. Restrict it by HTTP referrer in the Google Cloud console.

**SS3-4 upgrade:** moved LatLongField::google_maps_api_key to environment var (see Config above)

## Usage

```php
use Restruct\SilverStripe\Forms\LatLongField;

$fields->addFieldToTab('Root.Main', $gps = LatLongField::create('GPS', 'Position (lat.,long.)'));
```

Without address fields, the editor types an address into the field itself and clicks the search
button; the map opens and the field's value is replaced with the coordinates. The marker can be
dragged, or the map double-clicked, to adjust them. The "×" button clears the value.

### Look up the address from other fields

Point the field at the form fields that hold the address. It then becomes read-only, and the search
button geocodes the combined value of those fields:

```php
$gps->setAddressInputFields(['StreetAddress', 'Postcode', 'City']);
// or one at a time, by name or by field
$gps->addAddressInputField('StreetAddress');
$gps->addAddressInputField($cityField);
```

`setAddressInputFields()` replaces the list; `addAddressInputField()` appends to it.

### Map options

Options are passed to the bundled jQuery location picker (`client/js/jquery.locationpicker.js`).
`setLocationPickerOptions()` merges into what is already set:

```php
$gps->setLocationPickerOptions([
    'defaultLat' => 52.16502,   // where the map opens when the field is empty (default: Rotterdam)
    'defaultLng' => 4.48318,
    'defaultZoom' => 12,        // default 15
    'maptype' => 'SATELLITE',   // a google.maps.MapTypeId name; default ROADMAP
    'css_width' => '486px',     // popup map size and styling: css_width, css_height,
    'css_height' => '300px',    // css_backgroundColor, css_border, css_borderRadius, css_padding,
]);                             // css_position, css_marginTop, css_display
```

### Defaults the field sets

When it renders, the field adds a `text` class (for CMS styling), a placeholder
(`(empty / no location yet)`) unless one is set, and a description with instructions unless it
already has a description or a right title.

### Provides global `$GMapsApiKey` template variable
```
<img src="//maps.googleapis.com/maps/api/staticmap?center=Leiden+NL&size=600x600&key=$GMapsApiKey" />
```

`$GMapsApiKey` is the browser key when one is set (see [Config](#config)).

### Static helpers

| Method | Returns |
|--------|---------|
| `LatLongField::validateLatLong($value)` | `true` for a `"lat,long"` string with numeric parts, latitude -90..90 and longitude -180..180 (whitespace around the parts is allowed) |
| `LatLongField::calCulateDistance($from, $to, $decimals = 0)` | distance in km between two `"lat,long"` strings (haversine), rounded to `$decimals`; `null` if either is not a valid coordinate |
| `LatLongField::GeoCode($address)` | the first result of the Google Geocoding API for `$address` as an array (`geometry.location` holds `lat`/`lng`), or `null`. Uses `GMAPS_API_KEY`, never the browser key |
| `LatLongField::gmaps_api_key($requirePrimaryKey = false)` | the browser key if set, else `GMAPS_API_KEY`; with `true`, always `GMAPS_API_KEY` |

## Assets

Constructing the field adds these requirements to the page:

* the Google Maps JavaScript API, with the browser key (see [Config](#config))
* `client/js/jquery.locationpicker.js` and `client/js/latlongfield.js`
* `client/css/latlongfield.css`

`client/` is exposed through `extra.expose` in `composer.json`, so the files are published under
`_resources/` by `silverstripe/vendor-plugin`.

## Running the tests

The suite needs a booted Silverstripe project. Require the module into one through a **symlinked**
path repository (`/tests` is `export-ignore`, so a dist install has no tests), add
`silverstripe/recipe-testing`, copy `phpunit.xml.dist` to the project root, then:

```
# Silverstripe 6: flush through the environment
SS_PHPUNIT_FLUSH=1 vendor/bin/phpunit --testsuite latlongfield

# Silverstripe 5: flush=1 must come AFTER a test path
vendor/bin/phpunit vendor/restruct/silverstripe-latlongfield/tests flush=1
```

`.github/workflows/ci.yml` builds exactly such a host project for each supported major.
