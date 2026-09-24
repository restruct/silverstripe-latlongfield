# Changelog

This is the `v1` line (Silverstripe 4 and 5), bug fixes only. Silverstripe 5 and 6 are supported
by 2.x on `main`.

## 1.0.7 (2026-09-25)

- `calCulateDistance()` threw a `TypeError` on a malformed coordinate (for example a value from a
  visitor's cookie); it now returns `null`. Values that 1.0.6 parsed only partly (trailing text, a
  third part, a decimal comma, a float instead of a string) also return `null` now, where they used
  to give a distance with a PHP warning. Valid coordinates give the same distance as before.
- A field without address input fields threw a `TypeError` on PHP 8 as soon as it rendered.
