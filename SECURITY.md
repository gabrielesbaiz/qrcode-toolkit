# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 1.x | Yes |

## Reporting a vulnerability

Email **gabriele@sbaiz.com** with a description, the package version, and a
reproduction if you have one. Please do not open a public issue.

You will get an acknowledgement within 5 working days and an assessment within
15. If the report is valid you will be credited in the release notes unless you
ask not to be.

## Scope

In scope:

- Any input accepted by `Support\LogoLoader` that reaches the filesystem or the
  network outside a local path the caller supplied: a URL or stream wrapper
  that is not rejected, a path that escapes `security.logo_paths`, or a file
  read that ignores the size limit.
- Any string that reaches SVG, PDF or EPS output without being escaped or
  validated, including caption text, colours, font families and gradient
  identifiers.
- A crafted logo that causes unbounded memory use or a crash rather than being
  refused.
- An EPC payload that is built from invalid input without being rejected —
  a failing IBAN checksum, a field over its limit, or both remittance forms
  at once.
- HTTP responses that omit `nosniff`, the sandbox policy or the inline
  disposition on SVG.

Out of scope:

- A QR code that encodes a URL, a payment or a contact card you chose to
  encode. The package encodes the string it is handed.
- Whether a decorative style scans on a particular device. Use
  `scannability()`, and test-scan real output before printing at volume.
- Enabling `security.allow_svg_logo` and supplying an SVG you control. That is
  the documented behaviour of the setting.
- Setting `security.min_contrast` to `0` and producing an unreadable code.
- Authorisation. The package does not know who is allowed to see a code.
