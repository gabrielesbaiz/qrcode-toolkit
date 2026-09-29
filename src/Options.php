<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit;

use Gabrielesbaiz\QrcodeToolkit\Enums\Ecc;
use Gabrielesbaiz\QrcodeToolkit\Enums\EyeStyle;
use Gabrielesbaiz\QrcodeToolkit\Enums\ModuleStyle;
use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Support\Cast;
use Gabrielesbaiz\QrcodeToolkit\Support\Cmyk;
use Gabrielesbaiz\QrcodeToolkit\Support\Color;

/**
 * Every setting a QR code has, validated once.
 *
 * fromArray() is the only place that ever sees a string key, and with() goes
 * back through it, so a value set fluently and a value set in configuration
 * fail in exactly the same way.
 */
final readonly class Options
{
    /**
     * The colour slots that accept a CMYK override for the print formats.
     */
    public const CMYK_SLOTS = ['foreground', 'background', 'eye_color', 'eye_inner_color', 'label_color', 'frame_color'];

    /**
     * Every recognised key, in the order toArray() writes them.
     *
     * The order is fixed because the fingerprint hashes the serialised form,
     * and the fingerprint is both the SVG gradient id and the cache key.
     */
    private const KEYS = [
        'size', 'margin', 'ecc', 'ecc_explicit', 'version', 'encoding', 'eci',
        'foreground', 'background', 'gradient',
        'modules', 'module_scale',
        'eyes', 'eye_color', 'eye_inner_color',
        'logo', 'logo_size', 'logo_padding', 'logo_background',
        'label', 'label_color', 'label_size', 'label_font',
        'frame', 'cmyk', 'min_contrast', 'payload_kind',
    ];

    /**
     * @param  array<string, Cmyk>  $cmyk
     */
    public function __construct(
        public int $size = 300,
        public int $margin = 4,
        public Ecc $ecc = Ecc::M,
        public bool $eccExplicit = false,
        public ?int $version = null,
        public string $encoding = 'UTF-8',
        public ?bool $eci = null,
        public string $foreground = '#000000',
        public ?string $background = '#ffffff',
        public ?Gradient $gradient = null,
        public ModuleStyle $modules = ModuleStyle::Square,
        public float $moduleScale = 1.0,
        public EyeStyle $eyes = EyeStyle::Square,
        public ?string $eyeColor = null,
        public ?string $eyeInnerColor = null,
        public ?string $logo = null,
        public float $logoSize = 0.2,
        public float $logoPadding = 0.0,
        public ?string $logoBackground = null,
        public ?string $label = null,
        public string $labelColor = '#000000',
        public int $labelSize = 16,
        public ?string $labelFont = null,
        public ?Frame $frame = null,
        public array $cmyk = [],
        public float $minContrast = 3.0,
        public ?string $payloadKind = null,
    ) {
        $this->guardContrast();
    }

    /**
     * Build from an array of snake_case keys.
     *
     * @param  array<string, mixed>  $options
     */
    public static function fromArray(array $options): self
    {
        foreach (array_keys($options) as $key) {
            if (! in_array($key, self::KEYS, true)) {
                throw QrException::unknownOption($key, self::KEYS);
            }
        }

        $has = static fn (string $key): bool => array_key_exists($key, $options) && $options[$key] !== null;

        // A transparent background is the one thing a null means, so the
        // key being present and null is meaningfully different from absent.
        $background = array_key_exists('background', $options) && $options['background'] === null
            ? null
            : Color::normalize('background', $options['background'] ?? '#ffffff');

        return new self(
            size: $has('size') ? Cast::int($options['size'], 'size', 16, 4096) : 300,
            margin: $has('margin') ? Cast::int($options['margin'], 'margin', 0, 32) : 4,
            ecc: $has('ecc') ? Cast::enum(Ecc::class, $options['ecc'], 'ecc') : Ecc::M,
            eccExplicit: $has('ecc_explicit') ? Cast::bool($options['ecc_explicit'], 'ecc_explicit') : $has('ecc'),
            version: $has('version') ? Cast::int($options['version'], 'version', 1, 40) : null,
            encoding: $has('encoding') && is_string($options['encoding']) ? $options['encoding'] : 'UTF-8',
            eci: array_key_exists('eci', $options) && $options['eci'] !== null ? Cast::bool($options['eci'], 'eci') : null,
            foreground: Color::normalize('foreground', $options['foreground'] ?? '#000000'),
            background: $background,
            gradient: $has('gradient') ? self::gradient($options['gradient']) : null,
            modules: $has('modules') ? Cast::enum(ModuleStyle::class, $options['modules'], 'modules') : ModuleStyle::Square,
            moduleScale: $has('module_scale') ? Cast::float($options['module_scale'], 'module_scale', 0.1, 1.0) : 1.0,
            eyes: $has('eyes') ? Cast::enum(EyeStyle::class, $options['eyes'], 'eyes') : EyeStyle::Square,
            eyeColor: $has('eye_color') ? Color::normalize('eye_color', $options['eye_color']) : null,
            eyeInnerColor: $has('eye_inner_color') ? Color::normalize('eye_inner_color', $options['eye_inner_color']) : null,
            logo: $has('logo') && is_string($options['logo']) ? $options['logo'] : null,
            logoSize: $has('logo_size') ? Cast::float($options['logo_size'], 'logo_size', 0.05, 0.3) : 0.2,
            logoPadding: $has('logo_padding') ? Cast::float($options['logo_padding'], 'logo_padding', 0.0, 4.0) : 0.0,
            logoBackground: $has('logo_background') ? Color::normalize('logo_background', $options['logo_background']) : null,
            label: $has('label') ? self::label($options['label']) : null,
            labelColor: Color::normalize('label_color', $options['label_color'] ?? '#000000'),
            labelSize: $has('label_size') ? Cast::int($options['label_size'], 'label_size', 6, 128) : 16,
            labelFont: $has('label_font') ? self::font($options['label_font']) : null,
            frame: $has('frame') ? self::frame($options['frame']) : null,
            cmyk: $has('cmyk') ? self::cmyk($options['cmyk']) : [],
            minContrast: $has('min_contrast') ? Cast::float($options['min_contrast'], 'min_contrast', 0.0, 21.0) : 3.0,
            payloadKind: $has('payload_kind') && is_string($options['payload_kind']) ? $options['payload_kind'] : null,
        );
    }

    /**
     * Build a copy with some keys replaced.
     *
     * Deliberately the slow path: it revalidates everything. That is what
     * buys a single validation code path for the whole package.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides): self
    {
        return self::fromArray(array_replace($this->toArray(), $overrides));
    }

    /**
     * Get the correction level that will actually be used.
     *
     * A logo destroys modules, so it forces at least H. An explicitly set
     * level is still honoured upward but never silently downward, and the
     * caller can see the difference through this method.
     */
    public function effectiveEcc(): Ecc
    {
        if ($this->logo === null) {
            return $this->ecc;
        }

        if ($this->payloadKind === 'epc' && $this->eccExplicit) {
            return $this->ecc;
        }

        return $this->ecc->atLeast(Ecc::H);
    }

    /**
     * Determine whether the correction level was raised for the logo.
     */
    public function eccWasRaised(): bool
    {
        return $this->effectiveEcc() !== $this->ecc;
    }

    /**
     * Get the colour the eye ring is painted.
     */
    public function resolvedEyeColor(): string
    {
        return $this->eyeColor ?? $this->foreground;
    }

    /**
     * Get the colour the pupil is painted.
     */
    public function resolvedEyeInnerColor(): string
    {
        return $this->eyeInnerColor ?? $this->resolvedEyeColor();
    }

    /**
     * Determine whether the output may be drawn without anti-aliasing.
     *
     * Every one of these has to hold. A single curve anywhere and crisp
     * edges turn the arc into a staircase.
     */
    public function prefersCrispEdges(): bool
    {
        return $this->modules->isPureSquare()
            && $this->eyes->isPureSquare()
            && $this->moduleScale === 1.0
            && (! $this->frame instanceof Frame || $this->frame->radius === 0.0);
    }

    /**
     * Get the CMYK override for a colour slot, if one was given.
     */
    public function cmykFor(string $slot): ?Cmyk
    {
        return $this->cmyk[$slot] ?? null;
    }

    /**
     * Get the contrast ratio between the body colour and the background.
     *
     * A transparent background is measured against white. Requiring it to
     * pass against black as well would reject plain black on transparent,
     * which is the commonest transparent case there is; what surface the
     * code actually lands on is the caller's business, and the report
     * returned by scannability() says which assumption was made.
     */
    public function contrastRatio(): float
    {
        $background = $this->background ?? '#ffffff';

        $foreground = $this->gradient?->weakestStop($background) ?? $this->foreground;

        return Color::contrast($foreground, $background);
    }

    /**
     * Get the canonical array form.
     *
     * Key order is fixed and every scalar is normalised, because this is
     * what the fingerprint hashes.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $cmyk = [];

        foreach ($this->cmyk as $slot => $value) {
            $cmyk[$slot] = $value->toArray();
        }

        ksort($cmyk);

        return [
            'size' => $this->size,
            'margin' => $this->margin,
            'ecc' => $this->ecc->value,
            'ecc_explicit' => $this->eccExplicit,
            'version' => $this->version,
            'encoding' => $this->encoding,
            'eci' => $this->eci,
            'foreground' => $this->foreground,
            'background' => $this->background,
            'gradient' => $this->gradient?->toArray(),
            'modules' => $this->modules->value,
            'module_scale' => $this->moduleScale,
            'eyes' => $this->eyes->value,
            'eye_color' => $this->eyeColor,
            'eye_inner_color' => $this->eyeInnerColor,
            'logo' => $this->logo,
            'logo_size' => $this->logoSize,
            'logo_padding' => $this->logoPadding,
            'logo_background' => $this->logoBackground,
            'label' => $this->label,
            'label_color' => $this->labelColor,
            'label_size' => $this->labelSize,
            'label_font' => $this->labelFont,
            'frame' => $this->frame?->toArray(),
            'cmyk' => $cmyk,
            'min_contrast' => $this->minContrast,
            'payload_kind' => $this->payloadKind,
        ];
    }

    /**
     * Get a short, stable hash of the whole option set.
     */
    public function fingerprint(): string
    {
        return substr(sha1(json_encode($this->toArray(), JSON_THROW_ON_ERROR)), 0, 12);
    }

    /**
     * Refuse a combination no scanner will read.
     */
    private function guardContrast(): void
    {
        if ($this->minContrast <= 0.0) {
            return;
        }

        $ratio = $this->contrastRatio();

        if ($ratio < $this->minContrast) {
            throw QrException::insufficientContrast($ratio, $this->minContrast);
        }
    }

    /**
     * Coerce the gradient option.
     */
    private static function gradient(mixed $value): Gradient
    {
        if ($value instanceof Gradient) {
            return $value;
        }

        if (! is_array($value)) {
            throw QrException::invalidOption('gradient', $value, ['an array with from, to, type and angle']);
        }

        /** @var array<string, mixed> $value */
        return Gradient::fromArray($value);
    }

    /**
     * Coerce the frame option.
     */
    private static function frame(mixed $value): Frame
    {
        if ($value instanceof Frame) {
            return $value;
        }

        if ($value === true) {
            return new Frame;
        }

        if (! is_array($value)) {
            throw QrException::invalidOption('frame', $value, ['an array with color, width and radius']);
        }

        /** @var array<string, mixed> $value */
        return Frame::fromArray($value);
    }

    /**
     * Coerce the CMYK overrides.
     *
     * @return array<string, Cmyk>
     */
    private static function cmyk(mixed $value): array
    {
        if (! is_array($value)) {
            throw QrException::invalidOption('cmyk', $value, ['an array keyed by colour slot']);
        }

        $out = [];

        /** @var array<string, mixed> $value */
        foreach ($value as $slot => $components) {
            if (! is_string($slot) || ! in_array($slot, self::CMYK_SLOTS, true)) {
                throw QrException::unknownOption('cmyk.'.(is_string($slot) ? $slot : '?'), self::CMYK_SLOTS);
            }

            if ($components instanceof Cmyk) {
                $out[$slot] = $components;

                continue;
            }

            if (! is_array($components)) {
                throw QrException::invalidOption('cmyk.'.$slot, $components, ['four numbers']);
            }

            /** @var array<int, float|int|string> $components */
            $out[$slot] = Cmyk::fromArray('cmyk.'.$slot, $components);
        }

        return $out;
    }

    /**
     * Coerce the label text.
     */
    private static function label(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            throw QrException::invalidOption('label', $value, ['a string']);
        }

        $label = trim((string) $value);

        if ($label === '') {
            return null;
        }

        if (mb_strlen($label) > 128) {
            throw QrException::outOfRange('label length', mb_strlen($label), 1, 128);
        }

        return $label;
    }

    /**
     * Coerce and check the label font path.
     */
    private static function font(mixed $value): string
    {
        if (! is_string($value)) {
            throw QrException::invalidOption('label_font', $value, ['a path to a .ttf or .otf file']);
        }

        $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));

        if (! in_array($extension, ['ttf', 'otf'], true)) {
            throw QrException::invalidOption('label_font', $value, ['a .ttf or .otf file']);
        }

        if (! is_file($value) || ! is_readable($value)) {
            throw QrException::because(sprintf('The label font [%s] does not exist or is not readable.', $value));
        }

        return $value;
    }
}
