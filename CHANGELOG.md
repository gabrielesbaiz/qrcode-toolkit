# Changelog

All notable changes to `qrcode-toolkit` will be documented in this file.

## 1.0.0 - 2026-09-29

First release.

### Added

- A documentation site, and a **QR Composer** on it: every option wired to a
  symbol that redraws as you move it, a live scannability report, and the
  builder chain, config profile, Blade tag and Artisan command it would take to
  produce what you see. Its encoder and SVG writer are ports of this package's,
  checked against them over 205 matrices and 131 option combinations, and the
  PHP it writes is run back through the package to confirm it renders the same
  bytes.
- Six module styles — `square`, `rounded`, `dots`, `diamond`, `vertical` and
  `horizontal` — and six eye styles: `square`, `rounded`, `circle`, `leaf`,
  `pointy` and `module`, the last drawing the finder pattern out of individual
  modules so a dotted code gets dotted eyes. The two eye styles with a front —
  `leaf` and `pointy` — turn with the finder pattern they belong to, so each
  eye aims at the corner of the symbol it sits in rather than all three
  aiming the same way.
  The capsule and diamond styles, and the frame, are additions to the shapes
  commonly available in PHP.
- One geometry, computed once in module units, driving four output formats:
  SVG, PNG, PDF and EPS. A style that works in one works in all four.
- Hand-written PDF and EPS writers with no third-party PDF dependency, and no
  timestamp in either, so output is byte-stable.
- `scannability()`, reporting the correction level actually used, the modules a
  logo erased, how much of the correction budget that spent, the contrast
  ratio, the smallest sensible print width, and a `good` / `marginal` / `risky`
  verdict, so the cost of a decorative style is visible before printing.
- A contrast guard that refuses a foreground within 3:1 of the background,
  configurable through `security.min_contrast` and disabled with `0`. Low
  contrast is the commonest cause of a code that will not scan, and it is not
  usually checked before rendering.
- CMYK colour for the PDF and EPS writers, per colour slot, because the point
  of a vector format is print.
- Logo knock-out applied to the matrix *before* shapes are decided, so rounded
  and capsule styles terminate cleanly at the hole rather than being clipped.
- A logo loader that accepts only local paths and `data:` URIs, blocks every
  URL scheme and PHP stream wrapper, checks the real media type against the
  claimed one, and guards against decompression bombs.
- Payload helpers: `url`, `wifi`, `vcard` (vCard 3.0, vCard 4.0 or MECARD),
  `calendar` (a VEVENT with times in UTC), `otpauth` (TOTP or HOTP, with the
  Base32 secret validated because a wrong one locks the user out), `email`
  (`mailto:` or `MATMSG:`), `phone`, `sms`, `geo`, and `epc` — a SEPA EPC QR
  (EPC069-12) with genuine mod-97 IBAN validation, enforced field limits and
  the specification's error-correction and logo rules.
- Per-eye colours: `eye_color` and `eye_inner_color` each take one colour or
  exactly three, one per finder pattern. A single colour still renders as one
  path, so the common case is unchanged.
- Birthdays on a contact card are normalised to ISO 8601 for vCard and to the
  compact form for MECARD, and an ambiguous date is refused rather than
  guessed at — Google Contacts silently drops a `BDAY` it cannot parse.
- Six Artisan commands: `qr:doctor` (what this machine can render and which
  PNG driver resolves, and why), `qr:check` (the readability report as a build
  gate), `qr:make`, `qr:batch` (one code per CSV row), `qr:profiles` (which
  draws a code in the terminal) and `qr:clear`. They share one set of styling
  flags, so a look worked out in `qr:check` transfers to the others unchanged.
- An immutable, `Macroable`, `Conditionable` builder implementing `Htmlable`,
  `Responsable` and `Stringable`.
- A `<x-qr>` Blade component with attribute-to-option mapping, nested
  dash attributes, and automatic `role` / `aria-label` / `<title>` handling.
- Named profiles, and opt-in caching keyed on content, canonical options,
  format, PNG driver and the logo file's identity.

### Notes

- The PNG driver is chosen by rasterising a test gradient rather than by
  checking whether ImageMagick lists SVG support. ImageMagick's internal MSVG
  renderer registers under the same name as librsvg and paints gradients flat
  black, so asking is not enough.
- An ECI header is emitted only when the content is not plain ASCII. An
  unnecessary ECI prefix stops many scanners reading the code at all; this was
  found by the scan-back suite, where `SMSTO:` and `mailto:` payloads failed to
  decode until it was made conditional.
