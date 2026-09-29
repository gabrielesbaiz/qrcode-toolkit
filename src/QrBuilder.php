<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit;

use Gabrielesbaiz\QrcodeToolkit\Enums\Ecc;
use Gabrielesbaiz\QrcodeToolkit\Enums\EyeStyle;
use Gabrielesbaiz\QrcodeToolkit\Enums\Format;
use Gabrielesbaiz\QrcodeToolkit\Enums\GradientType;
use Gabrielesbaiz\QrcodeToolkit\Enums\ModuleStyle;
use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Support\CacheSpec;
use Gabrielesbaiz\QrcodeToolkit\Support\Cast;
use Gabrielesbaiz\QrcodeToolkit\Support\Cmyk;
use Gabrielesbaiz\QrcodeToolkit\Support\RenderContext;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Stringable;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * The fluent, immutable QR builder.
 *
 * Nothing is encoded or drawn until you ask for bytes.
 */
final class QrBuilder implements Htmlable, Responsable, Stringable
{
    use Conditionable;
    use Macroable {
        __call as macroCall;
    }

    /**
     * Bumped whenever a renderer changes shape, to retire stale cache entries.
     */
    private const CACHE_VERSION = 1;

    public function __construct(
        private readonly string $content,
        private readonly Options $options,
        private readonly RenderContext $context,
        private readonly CacheSpec $cache,
    ) {}

    /**
     * Get the content that will be encoded.
     */
    public function content(): string
    {
        return $this->content;
    }

    /**
     * Get a copy that encodes different content.
     */
    public function withContent(string $content): self
    {
        return new self($content, $this->options, $this->context, $this->cache);
    }

    /**
     * Get the options as they stand.
     */
    public function options(): Options
    {
        return $this->options;
    }

    /**
     * Get a copy with some options replaced.
     *
     * @param  array<string, mixed>  $options
     */
    public function with(array $options): self
    {
        return new self($this->content, $this->options->with($options), $this->context, $this->cache);
    }

    /**
     * Set the output width in pixels.
     */
    public function size(int $pixels): self
    {
        return $this->with(['size' => $pixels]);
    }

    /**
     * Set the quiet zone, in modules.
     */
    public function margin(int $modules): self
    {
        return $this->with(['margin' => $modules]);
    }

    /**
     * Set the error correction level.
     */
    public function errorCorrection(Ecc|string $level): self
    {
        return $this->with([
            'ecc' => Cast::enum(Ecc::class, $level, 'ecc')->value,
            'ecc_explicit' => true,
        ]);
    }

    /**
     * Pin the symbol version, or let it be chosen.
     */
    public function version(?int $version): self
    {
        return $this->with(['version' => $version]);
    }

    /**
     * Set the module colour, clearing any gradient.
     */
    public function color(string $foreground): self
    {
        return $this->with(['foreground' => $foreground, 'gradient' => null]);
    }

    /**
     * Fill the modules with a gradient.
     */
    public function gradient(string $from, string $to, GradientType|string $type = GradientType::Linear, float $angle = 0.0): self
    {
        return $this->with(['gradient' => [
            'from' => $from,
            'to' => $to,
            'type' => Cast::enum(GradientType::class, $type, 'gradient.type')->value,
            'angle' => $angle,
        ]]);
    }

    /**
     * Fill the modules with a radial gradient.
     */
    public function radialGradient(string $from, string $to): self
    {
        return $this->gradient($from, $to, GradientType::Radial);
    }

    /**
     * Set the background colour, or null for transparent.
     */
    public function background(?string $color): self
    {
        return $this->with(['background' => $color]);
    }

    /**
     * Drop the background.
     */
    public function transparent(bool $transparent = true): self
    {
        return $this->with(['background' => $transparent ? null : '#ffffff']);
    }

    /**
     * Set the module shape, and optionally how much of its cell it fills.
     */
    public function modules(ModuleStyle|string $style, ?float $scale = null): self
    {
        $options = ['modules' => Cast::enum(ModuleStyle::class, $style, 'modules')->value];

        if ($scale !== null) {
            $options['module_scale'] = $scale;
        }

        return $this->with($options);
    }

    /**
     * Set the finder pattern shape and colours.
     */
    public function eyes(EyeStyle|string $style, ?string $color = null, ?string $innerColor = null): self
    {
        return $this->with([
            'eyes' => Cast::enum(EyeStyle::class, $style, 'eyes')->value,
            'eye_color' => $color ?? $this->options->eyeColor,
            'eye_inner_color' => $innerColor ?? $this->options->eyeInnerColor,
        ]);
    }

    /**
     * Put a logo in the middle.
     *
     * The path must be local or a data URI; see the security notes. Adding
     * a logo raises the error correction to H unless the payload pins it.
     */
    public function logo(string $pathOrDataUri, ?float $size = null, ?float $padding = null, ?string $background = null): self
    {
        if ($this->options->payloadKind === 'epc') {
            throw QrException::because(
                'The EPC specification does not allow a logo over a payment code, because a bank app must be able to '
                .'read it first time. Render the logo beside the code instead.',
            );
        }

        return $this->with(array_filter([
            'logo' => $pathOrDataUri,
            'logo_size' => $size,
            'logo_padding' => $padding,
            'logo_background' => $background,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * Put a caption under the code.
     */
    public function label(string $text, ?int $size = null, ?string $color = null, ?string $font = null): self
    {
        return $this->with(array_filter([
            'label' => $text,
            'label_size' => $size,
            'label_color' => $color,
            'label_font' => $font,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * Draw a border around the code.
     */
    public function frame(?string $color = null, ?float $width = null, ?float $radius = null): self
    {
        return $this->with(['frame' => array_filter([
            'color' => $color,
            'width' => $width,
            'radius' => $radius,
        ], static fn (mixed $value): bool => $value !== null)]);
    }

    /**
     * Give a colour slot an exact CMYK value for the print formats.
     *
     * @param  array<int, float|int>  $components
     */
    public function cmyk(string $slot, array $components): self
    {
        $existing = $this->options->cmyk;
        $existing[$slot] = Cmyk::fromArray('cmyk.'.$slot, $components);

        return $this->with(['cmyk' => $existing]);
    }

    /**
     * Cache the rendered bytes.
     */
    public function cache(?int $ttl = null, ?string $store = null, ?string $key = null): self
    {
        return new self($this->content, $this->options, $this->context, $this->cache->with($ttl, $store, $key, enabled: true));
    }

    /**
     * Render this code without touching the cache.
     */
    public function noCache(): self
    {
        return new self($this->content, $this->options, $this->context, $this->cache->disabled());
    }

    /**
     * Report what this styling costs in readability.
     */
    public function scannability(): Scannability
    {
        return $this->context->scannability($this->content, $this->options);
    }

    /**
     * Render to the given format.
     */
    public function render(Format|string|null $format = null): string
    {
        $format = $format === null ? Format::Svg : Cast::enum(Format::class, $format, 'format');

        return $this->cache->remember(
            $this->cacheKey($format),
            fn (): string => $this->context->renderer($format)->render(
                $this->context->layout($this->content, $this->options),
                $this->context->logos()->load($this->options->logo),
            ),
        );
    }

    /**
     * Render to SVG.
     */
    public function toSvg(): string
    {
        return $this->render(Format::Svg);
    }

    /**
     * Render to PNG.
     */
    public function toPng(): string
    {
        return $this->render(Format::Png);
    }

    /**
     * Render to PDF.
     */
    public function toPdf(): string
    {
        return $this->render(Format::Pdf);
    }

    /**
     * Render to EPS.
     */
    public function toEps(): string
    {
        return $this->render(Format::Eps);
    }

    /**
     * Render to a data URI.
     *
     * SVG can be percent encoded instead of base64, which is smaller and
     * leaves the markup greppable in the page source.
     */
    public function toDataUri(Format|string|null $format = null, bool $base64 = true): string
    {
        $format = $format === null ? Format::Svg : Cast::enum(Format::class, $format, 'format');
        $payload = $this->render($format);

        if (! $base64 && $format === Format::Svg) {
            return 'data:'.$format->mimeType().';charset=UTF-8,'.rawurlencode($payload);
        }

        return 'data:'.$format->mimeType().';base64,'.base64_encode($payload);
    }

    /**
     * Render as an img element pointing at a data URI.
     *
     * @param  array<string, scalar>  $attributes
     */
    public function toImg(array $attributes = [], Format|string|null $format = null): HtmlString
    {
        $attributes = array_merge(['alt' => '', 'loading' => 'lazy', 'decoding' => 'async'], $attributes);
        $attributes['src'] = $this->toDataUri($format);

        $rendered = [];

        foreach ($attributes as $name => $value) {
            if (preg_match('/^[a-zA-Z][\w:-]*$/', (string) $name) !== 1) {
                throw QrException::because(sprintf('[%s] is not a valid HTML attribute name.', (string) $name));
            }

            $rendered[] = $name.'="'.htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        return new HtmlString('<img '.implode(' ', $rendered).' />');
    }

    /**
     * Render as inline SVG.
     *
     * Inline rather than an img element, because that is what a Htmlable
     * should give Blade: it is styleable with CSS and avoids a second
     * base64 expansion. The renderer has already escaped everything in it.
     */
    public function toHtml(): string
    {
        return $this->toSvg();
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }

    /**
     * Build an HTTP response.
     *
     * @param  array<string, string>  $headers
     */
    public function response(Format|string|null $format = null, int $status = 200, array $headers = []): Response
    {
        $format = $format === null ? Format::Svg : Cast::enum(Format::class, $format, 'format');
        $payload = $this->render($format);

        return new Response($payload, $status, array_merge([
            'Content-Type' => $format->contentTypeHeader(),
            'Content-Length' => (string) strlen($payload),
            'Content-Disposition' => 'inline',
            // An SVG served from your own origin is a same-origin
            // document, so it is served as inert as the headers allow.
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => '"'.sha1($payload).'"',
        ], $headers));
    }

    /**
     * Build a download response.
     *
     * @param  array<string, string>  $headers
     */
    public function download(string $filename = 'qrcode', Format|string|null $format = null, array $headers = []): Response
    {
        $format = $format === null ? Format::Svg : Cast::enum(Format::class, $format, 'format');

        $filename = Str::of($filename)->basename()->trim()->value();

        if ($filename === '') {
            $filename = 'qrcode';
        }

        if (! str_ends_with(strtolower($filename), '.'.$format->extension())) {
            $filename .= '.'.$format->extension();
        }

        return $this->response($format, 200, array_merge([
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $filename,
                (string) Str::ascii($filename),
            ),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ], $headers));
    }

    /**
     * Respond with the code when returned from a controller.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        return $this->response();
    }

    /**
     * Write the code to a path, on a disk or on the local filesystem.
     */
    public function save(string $path, ?string $disk = null): string
    {
        $format = Format::fromExtension(pathinfo($path, PATHINFO_EXTENSION))
            ?? throw QrException::indeterminateFormat($path);

        $payload = $this->render($format);

        if ($disk !== null) {
            if (Storage::disk($disk)->put($path, $payload) === false) {
                throw QrException::writeFailed($path, $disk);
            }

            return $path;
        }

        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            throw QrException::writeFailed($path);
        }

        if (@file_put_contents($path, $payload, LOCK_EX) === false) {
            throw QrException::writeFailed($path);
        }

        return $path;
    }

    /**
     * Forward unknown calls to macros, with a message worth reading.
     *
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (! self::hasMacro($method)) {
            throw QrException::unknownMethod($method);
        }

        $result = $this->macroCall($method, $parameters);

        // A macro that forgets to return breaks the chain three calls
        // later, where the cause is no longer obvious.
        if ($result === null) {
            throw QrException::macroMustReturn($method);
        }

        return $result;
    }

    /**
     * Build the cache key.
     *
     * Everything that can change a byte of the output goes into it,
     * including the PNG driver, because GD and ImageMagick do not produce
     * the same file.
     */
    private function cacheKey(Format $format): string
    {
        return sha1(json_encode([
            'v' => self::CACHE_VERSION,
            'content' => $this->content,
            'format' => $format->value,
            'options' => $this->options->toArray(),
            'logo' => $this->context->logos()->fingerprint($this->options->logo),
            'driver' => $format === Format::Png ? $this->context->pngDriver() : null,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
