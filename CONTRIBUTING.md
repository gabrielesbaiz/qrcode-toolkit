# Contributing

Thank you for considering a contribution.

## Before you open a pull request

Run all four. They are the contract, and CI runs the same:

```bash
composer test        # Pest
composer analyse     # PHPStan, level max — must stay clean
composer format      # Pint
composer rector-dry  # Rector
```

PHPStan runs at **level max** with no baseline. Please do not add
`@phpstan-ignore` comments, baseline entries or `assert()` calls to silence an
error — fix the underlying type instead. If an error genuinely cannot be fixed,
say so in the pull request and we will look at it together.

## Tests

- A bug fix should come with a test that fails without it.
- A new option needs a unit test on `Options` covering its range and its
  rejection message, and a feature test that renders with it.
- **A new module or eye style must be added to the scan-back suite.** A style
  that cannot be decoded back to its input is not a style, it is a picture.
  `tests/Feature/ScanBackTest.php` is the file, and it renders at several sizes
  because the decoder is more size-sensitive than a real scanner.
- Every example in the README is executed by
  `tests/Feature/ReadmeExamplesTest.php`. If you change a documented sample,
  change it there too, or the suite will tell you.

## Things worth knowing before you change the renderers

- Output is **byte-stable** on purpose: the cache key and the golden-file tests
  both depend on it. No timestamps, no `uniqid()`, no counters, and every float
  goes through `Support\Num::f()`, which exists mainly to normalise negative
  zero away.
- The geometry layer works in **module units** and knows nothing about pixels
  or formats. Renderers receive a `LayoutResult` and the options, never a
  matrix and never a Bacon type. Keep that seam.
- Corners carry their elbow as well as their end point. That is what lets the
  SVG, PDF, EPS and GD writers draw the same curve rather than four similar
  ones.
- `Support\LogoLoader` is a security boundary. Changes to it need a test per
  check, and the checks run in a deliberate order — the scheme is rejected
  before any filesystem call, and `realpath()` runs before the allowlist.

## Style

Pint with the project preset, British English in prose, and a docblock on every
method. Comments should explain why something is the way it is, not restate
what the line does.
