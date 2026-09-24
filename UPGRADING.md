# Upgrading

## 1.x to 2.0

2.0 supports Silverstripe 5 and 6 on PHP 8.1+. On Silverstripe 4, keep a `^1` constraint:
Composer will not offer 2.0 there.

On Silverstripe 5 and 6, change your constraint to `^2`:

```bash
composer require restruct/silverstripe-latlongfield:^2
```

The class name and namespace (`Restruct\SilverStripe\Forms\LatLongField`), the constructor, the
environment variables and the `$GMapsApiKey` template variable are unchanged. Nothing to edit in
`_config` or templates.

Flush after upgrading so the changed field template is recompiled (`?flush=1`, or
`vendor/bin/sake dev/build flush=1` on Silverstripe 5 and `vendor/bin/sake db:build --flush` on
Silverstripe 6).

### Behaviour you may notice

- **`validateLatLong()`** now requires numeric parts within range (latitude -90..90, longitude
  -180..180). Values such as `"52abc,4"` or `"999,999"` that 1.x accepted are now rejected, and
  coordinates on the equator or prime meridian (`"0,5.1"`) that 1.x rejected are now accepted.
- **`calCulateDistance()`** returns `null` for an invalid coordinate instead of throwing a
  `TypeError`. Values that 1.x parsed only partly (trailing text, a third part, a decimal comma, a
  float instead of a string) also return `null` now, where they used to give a distance with a PHP
  warning. If you show its result, handle the empty case.
- **If you override the `LatLongField` template**: the data attributes now use
  `$AddressInputFieldsJSON` and `$LocationPickerOptionsJSON`. The old `$AddressInputFields.JSON`
  form renders empty on Silverstripe 6.
  The button markup also differs per major now: Bootstrap 4 wrappers on Silverstripe 5, flat
  Bootstrap 5 markup on Silverstripe 6 (`$UsesBootstrap4InputGroup` in the template).
