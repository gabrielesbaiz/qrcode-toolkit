<p align="center">
    <img src="art/qrcode-toolkit-logo.png" alt="QrcodeToolkit" width="600">
</p>

# QrcodeToolkit

A QR code generator for Laravel that tells you what your styling costs — `dots` modules, a `leaf` eye, a gradient and a logo, and a report that says the code still has 74.6% of its error-correction budget left.

[![Latest version](https://img.shields.io/packagist/v/gabrielesbaiz/qrcode-toolkit.svg?style=flat-square)](https://packagist.org/packages/gabrielesbaiz/qrcode-toolkit)
[![PHP](https://img.shields.io/packagist/dependency-v/gabrielesbaiz/qrcode-toolkit/php?style=flat-square)](composer.json)
[![Laravel](https://img.shields.io/packagist/dependency-v/gabrielesbaiz/qrcode-toolkit/illuminate%2Fsupport?style=flat-square&label=laravel)](composer.json)
[![Downloads](https://img.shields.io/packagist/dt/gabrielesbaiz/qrcode-toolkit.svg?style=flat-square)](https://packagist.org/packages/gabrielesbaiz/qrcode-toolkit)
[![Stars](https://img.shields.io/github/stars/gabrielesbaiz/qrcode-toolkit?style=flat-square&logo=github)](https://github.com/gabrielesbaiz/qrcode-toolkit/stargazers)
[![Sponsor](https://img.shields.io/github/sponsors/gabrielesbaiz?style=flat-square&label=sponsor&logo=github)](https://github.com/sponsors/gabrielesbaiz)

### 📖 [Read the documentation →](https://gabrielesbaiz.github.io/qrcode-toolkit/)

Every module shape, eye shape, gradient, payload and output format with a
working example beside the code it produces, the full option reference, and the
comparison table that says when to reach for something else.

> [!IMPORTANT]
> A ⭐ costs you nothing and helps other developers find this package.
> [Sponsoring](https://github.com/sponsors/gabrielesbaiz) keeps it compatible
> with every new Laravel release.

## What it does

`bacon/bacon-qr-code` already renders rounded modules, dot modules, five eye
classes, per-eye two-tone fills, five gradient presets and CMYK. If the styling
you want is in that list, use it directly and skip the wrapper — this package
uses it too, for encoding, and nothing else. What it adds is the layer around
the shapes:

- **One geometry drives four formats.** SVG, PNG, PDF and EPS come out of the same shape list, so a rounded eye is the same rounded eye in all of them. Everywhere else the backends diverge in what they can draw.
- **A scannability report.** Error-correction level used, modules erased by the logo, how much of the correction budget that spent, contrast ratio, and module size in millimetres at a given print width — before you print ten thousand of them.
- **A contrast guard that refuses.** `#eeeeee` on `#ffffff` throws rather than rendering a code nothing can read.
- **The logo is punched out of the matrix before shapes are decided,** so capsules and rounded corners stop cleanly at its edge instead of being clipped mid-stroke.
- **Twelve payload helpers**, including a SEPA EPC QR that validates the IBAN with mod-97 first.
- **Twelve checks on every logo file.** Local paths and base64 data URIs only — never a remote URL, never a stream wrapper.

Decoration costs readability, and this package makes the cost visible rather
than making it disappear. The report and the guard are guidance drawn from the
symbol itself; they cannot know your printer, your paper or your scanner, and
nothing replaces scanning real output on a real phone.

## Requirements

- PHP 8.2, 8.3 or 8.4
- Laravel 11, 12 or 13
- `ext-gd` **or** `ext-imagick`, but only if you want PNG. SVG, PDF and EPS need
  neither.

## Installation

```bash
composer require gabrielesbaiz/qrcode-toolkit

php artisan vendor:publish --tag=qrcode-toolkit-config

php artisan qr:doctor
```

The service provider is auto-discovered and the publish step is optional: the
defaults produce working codes untouched. There are no migrations, no tables and
no assets. `qr:doctor` reports which PNG driver resolved and why.

**[Full installation guide →](https://gabrielesbaiz.github.io/qrcode-toolkit/#/install)**

## Artisan commands

| Command | What it does |
|---|---|
| `qr:make {content}` | Renders to `--out`, or writes SVG to stdout. Takes every styling flag. |
| `qr:check {content}` | Prints the scannability report. `--fail-on=marginal\|risky` makes it a CI gate. |
| `qr:doctor` | Extensions, the resolved PNG driver **and why**, font path, cache store, security settings. |
| `qr:batch {csv}` | One code per row of a CSV. `--column`, `--name-column`, `--disk`, `--check`. |
| `qr:profiles {profile?}` | Lists configured profiles and draws one in block characters. |
| `qr:clear` | Forgets cached codes, without flushing a cache it cannot tag. |

Every flag is on the
[commands page](https://gabrielesbaiz.github.io/qrcode-toolkit/#/commands).

## Documentation

| | |
|---|---|
| [Documentation site](https://gabrielesbaiz.github.io/qrcode-toolkit/) | Everything: install, configure, style, operate. |
| [Quick start](https://gabrielesbaiz.github.io/qrcode-toolkit/#/quickstart) | From `composer require` to a styled code. |
| [Styling](https://gabrielesbaiz.github.io/qrcode-toolkit/#/styling) | Every shape, colour, gradient, logo and frame, with the output beside it. |
| [Payloads](https://gabrielesbaiz.github.io/qrcode-toolkit/#/payloads) | All twelve helpers and the exact string each one encodes. |
| [Output formats](https://gabrielesbaiz.github.io/qrcode-toolkit/#/formats) | SVG, PNG, PDF, EPS, CMYK, and getting the bytes out. |
| [Scannability](https://gabrielesbaiz.github.io/qrcode-toolkit/#/scannability) | The report, the contrast guard, and what the verdicts mean. |
| [API reference](https://gabrielesbaiz.github.io/qrcode-toolkit/#/api) | Every method on the facade and the builder. |
| [Security](https://gabrielesbaiz.github.io/qrcode-toolkit/#/security) | The logo gate, the escaping rules, and what is out of scope. |
| [CHANGELOG.md](CHANGELOG.md) | What changed, and when. |

The site is in [`docs/`](docs/) and is served by GitHub Pages.

## Testing

```bash
composer test        # Pest — 470 tests
composer analyse     # PHPStan, level max, no baseline
composer format      # Pint
composer rector-dry  # no pending refactors
```

Those four are the contract, and CI runs the suite across PHP 8.2–8.4 and
Laravel 11–13. The one that matters most is the scan-back suite inside
`composer test`: every module shape, eye shape, gradient and logo variant is
rendered to PNG and decoded back to its input, at several sizes, with a negative
control that must **not** decode.

## Contributing

Thank you for considering contributing. The guide is in
[CONTRIBUTING.md](CONTRIBUTING.md).

## Security vulnerabilities

Please review [SECURITY.md](SECURITY.md) for reporting a vulnerability. Please
do not open a public issue.

## Credits

Written and maintained by [Gabriele Sbaiz](https://github.com/gabrielesbaiz).

Encoding is [`bacon/bacon-qr-code`](https://github.com/Bacon/BaconQrCode) by Ben
Scholzen and DASPRiD, which does the hard part. The package is built on Laravel
and [`spatie/laravel-package-tools`](https://github.com/spatie/laravel-package-tools),
and its scan-back suite uses
[`khanamiryan/qrcode-detector-decoder`](https://github.com/khanamiryan/php-qrcode-detector-decoder).

## Support this package

If it is useful to you:

- ⭐ **Star the repo.** Free, thirty seconds, and it is the first signal other developers look at.
- ❤️ **[Become a sponsor](https://github.com/sponsors/gabrielesbaiz).** From $5 a month.
- 🐛 **Open a good issue.** A clear reproduction is worth more than you think.
- 🗣️ **Tell another Laravel developer.** Word of mouth is how packages survive.

[![Sponsor on GitHub](https://img.shields.io/badge/Sponsor-gabrielesbaiz-ff69b4?style=for-the-badge&logo=github-sponsors)](https://github.com/sponsors/gabrielesbaiz)

## Disclaimer

This package is provided **as is**, without warranty of any kind, express or
implied, including but not limited to the warranties of merchantability,
fitness for a particular purpose, title and non-infringement. To the fullest
extent permitted by applicable law, in no event shall the authors, copyright
holders or contributors be liable for any claim, damages or other liability —
whether in an action of contract, tort or otherwise — arising from, out of or in
connection with this package or its use, including without limitation any
direct, indirect, incidental, special, exemplary, consequential or punitive
damages, loss of data, loss of profits, business interruption, or any failure of
a generated code to be read by any particular scanner.

This package lets you make QR codes that are more decorative than a plain one,
and decoration costs readability. The scannability report and the contrast guard
exist to make that trade visible, and they are guidance rather than a guarantee:
they cannot know what printer, what paper, what lighting or what scanner your
code will meet. Whoever deploys it is responsible for deciding whether a given
style is acceptable. That responsibility includes, and is not limited to,
test-scanning real output on real devices before printing at volume, checking
payment payloads against the receiving bank before sending an invoice, keeping
logo files out of the reach of untrusted input, and reviewing the code yourself
before putting it in front of something you cannot afford to get wrong. Nothing
here constitutes security, financial, legal or compliance advice.

Use of this package is entirely at your own risk.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT. See [LICENSE.md](LICENSE.md). The MIT licence's warranty disclaimer and
limitation of liability apply in full, alongside the disclaimer above.
