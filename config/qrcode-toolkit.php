<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | Every option a QR code has, with the value used when you do not say
    | otherwise. Qr::make() starts from this array, a profile is merged over
    | it, and a fluent call is merged over that. Anything set here never has
    | to be repeated at a call site.
    |
    */

    'defaults' => [

        /*
         * The output width in pixels. For SVG it is the viewport the code is
         * drawn into; the geometry itself is resolution free. A code with a
         * caption is taller than it is wide, because the caption is added
         * below rather than taken out of the symbol.
         */
        'size' => 300,

        /*
         * The quiet zone, in MODULES rather than pixels, because that is how
         * a scanner's requirement is written. Four is the specified minimum;
         * less will fail on some handheld hardware.
         */
        'margin' => 4,

        /*
         * Error correction: L is about 7%, M 15%, Q 25%, H 30%. More
         * correction means a denser symbol for the same content. Adding a
         * logo raises this to H automatically and says so.
         */
        'ecc' => 'M',

        /*
         * The symbol version, 1 (21x21) through 40 (177x177). Null picks the
         * smallest that fits, which is almost always right; pin it only when
         * a batch of codes has to come out physically identical in size.
         */
        'version' => null,

        /*
         * The character encoding, and whether to announce it with an ECI
         * header.
         *
         * Null means decide per payload: plain ASCII is sent without an
         * ECI header, and anything else gets one. That is not fussiness.
         * An unnecessary ECI prefix stops a good many scanners from
         * reading the code at all, and ASCII does not need one. Set it to
         * true or false to override the decision.
         */
        'encoding' => 'UTF-8',
        'eci' => null,

        /*
         * The dark module colour. Hex only: #rgb or #rrggbb. Named colours,
         * rgb() and alpha are deliberately not accepted, so that there is
         * exactly one way to say transparent, which is a null background.
         */
        'foreground' => '#000000',

        /*
         * The light module colour, or null for a transparent background.
         * Transparency works in SVG and PNG and is ignored by PDF and EPS.
         */
        'background' => '#ffffff',

        /*
         * Fill the modules with a gradient rather than a flat colour. Set it
         * to null for no gradient.
         *
         *   'gradient' => [
         *       'type'  => 'linear',   // linear or radial
         *       'from'  => '#4338ca',
         *       'to'    => '#be185d',
         *       'angle' => 45,         // degrees, linear only
         *   ],
         *
         * PDF and EPS have no gradients, so they use the first stop and say
         * so in the render warnings.
         */
        'gradient' => null,

        /*
         * The shape of a data module: square, rounded, dots, diamond,
         * vertical or horizontal. Everything except square costs some of
         * the error correction budget, so check scannability() before
         * printing a decorative style small.
         */
        'modules' => 'square',

        /*
         * How much of its cell a module fills, 0.1 to 1.0. Below about 0.65
         * the code starts to struggle on cheap scanners, and the
         * scannability report will say so.
         */
        'module_scale' => 1.0,

        /*
         * The three finder patterns: square, rounded, circle, leaf, pointy
         * or module. A module eye is drawn out of individual modules in
         * whatever shape the body uses, rather than as a ring with a hole.
         *
         * Each colour takes one value, or exactly three — one per finder
         * pattern, top-left, top-right then bottom-left. Left null they
         * fall back to the foreground.
         */
        'eyes' => 'square',
        'eye_color' => null,
        'eye_inner_color' => null,

        /*
         * A logo in the middle.
         *
         * The path must be a local file or a data: URI. Remote URLs are
         * refused outright; see the security section below for why.
         *
         * logo_size is a fraction of the SYMBOL, 0.05 to 0.3, so changing
         * the margin never resizes the logo. logo_padding is in modules,
         * like the frame width. The modules underneath are removed rather
         * than painted over, which is cleaner and identical to a decoder.
         */
        'logo' => null,
        'logo_size' => 0.2,
        'logo_padding' => 0.0,
        'logo_background' => null,

        /*
         * A caption under the code, never over it. PNG captions want a
         * TrueType font; see the font section. SVG can only name a family
         * and hope the viewer has it, which is why PNG is the better format
         * when the exact face matters.
         */
        'label' => null,
        'label_color' => '#000000',
        'label_size' => 16,
        'label_font' => null,

        /*
         * A border around the whole code.
         *
         *   'frame' => ['color' => '#0f172a', 'width' => 1, 'radius' => 2],
         *
         * Width and radius are in modules.
         */
        'frame' => null,

        /*
         * Rewrite http and https URLs into their uppercase form when that
         * shortens the payload. Scheme and host are case insensitive, and an
         * uppercase payload can fall into the alphanumeric QR mode and drop
         * a whole symbol version. The path is never touched. Off by default
         * because a few badly behaved endpoints do care about scheme case.
         */
        'optimize_url' => false,

    ],

    /*
    |--------------------------------------------------------------------------
    | Profiles
    |--------------------------------------------------------------------------
    |
    | Named option sets, merged recursively over the defaults. Reach for one
    | with Qr::profile('brand') or <x-qr profile="brand" />, so that a
    | rebrand is one file to change rather than a grep across your views.
    |
    */

    'profiles' => [

        /*
         * Marketing material: decorative, on brand, and generous with error
         * correction because the rounded modules and the logo both spend it.
         */
        'brand' => [
            'size' => 512,
            'ecc' => 'H',
            'modules' => 'dots',
            'module_scale' => 0.9,
            'eyes' => 'rounded',
            'gradient' => [
                'type' => 'linear',
                'from' => '#4338ca',
                'to' => '#be185d',
                'angle' => 45,
            ],
        ],

        /*
         * A ticket or a boarding pass: printed small, scanned by a handheld
         * gun in bad light, with the human readable code underneath.
         * Everything decorative is off on purpose.
         */
        'ticket' => [
            'size' => 240,
            'margin' => 4,
            'ecc' => 'Q',
            'modules' => 'square',
            'foreground' => '#000000',
            'background' => '#ffffff',
            'label_size' => 12,
            'label_color' => '#475569',
        ],

        /*
         * The smallest, most readable code this package makes. Good for
         * anything embedded in a document or an email.
         */
        'minimal' => [
            'size' => 180,
            'margin' => 2,
            'ecc' => 'L',
            'background' => null,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Rendering is deterministic: the same content, options and format always
    | produce the same bytes. Caching therefore trades a little memory for
    | CPU, which pays off as soon as a code is drawn inside a loop or on a
    | hot page.
    |
    | Payloads are stored base64 encoded so that binary PNG and PDF output
    | survives every cache driver, including database and memcached.
    |
    | The key covers the content, the canonical options, the format, the PNG
    | driver, and the logo file's path, size and modification time, so
    | replacing the logo file invalidates the entries that used it.
    |
    */

    'cache' => [

        'enabled' => env('QRCODE_TOOLKIT_CACHE', false),

        /*
         * Null uses the application's default store.
         */
        'store' => env('QRCODE_TOOLKIT_CACHE_STORE'),

        /*
         * Seconds. Zero caches forever, which is safe given the key, but
         * leaves pruning to you.
         */
        'ttl' => 60 * 60 * 24 * 30,

        'prefix' => 'qrcode-toolkit:',

    ],

    /*
    |--------------------------------------------------------------------------
    | PNG driver
    |--------------------------------------------------------------------------
    |
    | auto prefers ImageMagick and falls back to GD. ImageMagick rasterises
    | our own SVG, so the logo, the caption and the gradient are already
    | right; GD redraws the geometry itself and samples the gradient per
    | module.
    |
    | The check is a real one: ImageMagick ships an internal SVG renderer
    | that registers under the same name as librsvg and quietly paints
    | gradients flat black, so the package rasterises a test gradient once
    | per process and only trusts ImageMagick if the result has two colours
    | in it. Naming a driver whose extension is missing throws rather than
    | silently downgrading.
    |
    */

    'png_driver' => env('QRCODE_TOOLKIT_PNG_DRIVER', 'auto'), // auto, imagick, gd

    /*
    |--------------------------------------------------------------------------
    | Font
    |--------------------------------------------------------------------------
    |
    | Captions in PNG output need a TrueType font, because GD's built-in
    | faces come in five fixed sizes and cannot render anything outside
    | Latin-1. Point path at a .ttf or .otf you have the right to ship.
    |
    | SVG cannot embed a font file, so it names a family and the viewer
    | resolves it: set family to a stack your audience actually has.
    |
    */

    'font' => [

        'path' => env('QRCODE_TOOLKIT_FONT_PATH'),

        'family' => 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',

    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | A logo is the one place this package reads bytes you did not write, so
    | it is the one place that needs a gate.
    |
    | A logo may come from a local path or a data: URI and from nowhere else.
    | A remote URL is refused: fetching one would turn every render into a
    | server-side request forgery primitive, and would let whoever controls
    | that host change your printed material after it was signed off. The
    | same rule blocks PHP's stream wrappers, which matter more: phar is an
    | object injection vector and php://filter an arbitrary file read.
    |
    | Each candidate is then checked for extension, size, real content type
    | as reported by fileinfo, agreement between the two, and decodable
    | dimensions, in that order.
    |
    */

    'security' => [

        /*
         * Restrict logos to these directories, matched after symlinks and
         * ../ have been resolved. An empty array allows any readable path,
         * which is fine while the path is a literal in your own code and
         * not fine the moment it can come from a request.
         */
        'logo_paths' => [
            // public_path('img'),
            // storage_path('app/logos'),
        ],

        'logo_max_bytes' => 1024 * 1024,

        'logo_extensions' => ['png', 'jpg', 'jpeg', 'gif', 'webp'],

        'logo_mimes' => ['image/png', 'image/jpeg', 'image/gif', 'image/webp'],

        /*
         * The largest logo accepted, per side. A forty kilobyte PNG can
         * declare itself forty thousand pixels square, and it is decoding
         * it that exhausts the memory.
         */
        'logo_max_dimension' => 4000,

        /*
         * SVG logos are off by default. An SVG is a document that can carry
         * script, external references and XML entities, and it is embedded
         * verbatim into your output. Turning this on runs the file through
         * a check for all of those, but a PNG is still the safe answer.
         */
        'allow_svg_logo' => false,

        /*
         * The minimum contrast ratio between the modules and the background.
         * Low contrast is the commonest cause of a code that will not scan,
         * and nothing else in the ecosystem checks for it. A gradient is
         * judged by whichever of its two stops fares worse, and a
         * transparent background is measured against white.
         *
         * Set to 0 to skip the check entirely.
         */
        'min_contrast' => 3.0,

    ],

];
