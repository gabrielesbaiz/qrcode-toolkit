<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Exceptions;

use InvalidArgumentException;

/**
 * Every error this package raises.
 *
 * The named constructors exist so that a message is written once and tested
 * once. A caller catching QrException catches all of them.
 */
class QrException extends InvalidArgumentException
{
    /**
     * The option key is not one this package knows.
     *
     * @param  array<int, string>  $known
     */
    public static function unknownOption(string $key, array $known): self
    {
        $suggestion = self::closest($key, $known);

        return new self(sprintf(
            'Unknown QR option [%s].%s',
            $key,
            $suggestion === null ? '' : sprintf(' Did you mean [%s]?', $suggestion),
        ));
    }

    /**
     * The value is outside the range the option accepts.
     */
    public static function outOfRange(string $key, mixed $value, int|float $min, int|float $max): self
    {
        return new self(sprintf(
            'Option [%s] must be between %s and %s. Got [%s].',
            $key,
            self::stringify($min),
            self::stringify($max),
            self::stringify($value),
        ));
    }

    /**
     * The value is not one of the accepted ones.
     *
     * @param  array<int, string|int>  $expected
     */
    public static function invalidOption(string $key, mixed $value, array $expected): self
    {
        return new self(sprintf(
            'Invalid value [%s] for option [%s]. Expected one of: %s.',
            self::stringify($value),
            $key,
            implode(', ', array_map(self::stringify(...), $expected)),
        ));
    }

    /**
     * The colour is not a hex colour.
     */
    public static function invalidColor(string $key, mixed $value): self
    {
        return new self(sprintf(
            'Option [%s] must be a hex colour such as #0af or #00aaff. Got [%s]. '
            .'Named colours, rgb() and alpha channels are not accepted; use a null background for transparency.',
            $key,
            self::stringify($value),
        ));
    }

    /**
     * The nested option is missing a key it requires.
     */
    public static function missingKey(string $key, string $missing): self
    {
        return new self(sprintf('Option [%s] requires the key [%s].', $key, $missing));
    }

    /**
     * The foreground and background are too close to scan reliably.
     */
    public static function insufficientContrast(float $ratio, float $required): self
    {
        return new self(sprintf(
            'The foreground and background contrast at %.2f:1, below the %.2f:1 minimum a scanner needs. '
            .'Darken the foreground, lighten the background, or set qrcode-toolkit.security.min_contrast to 0 to skip this check.',
            $ratio,
            $required,
        ));
    }

    /**
     * The forced version is too small for the content at this correction level.
     */
    public static function versionTooSmall(int $version, string $reason): self
    {
        return new self(sprintf(
            'Version %d is too small for this content at the chosen error correction level (%s). '
            .'Raise the version, lower the error correction, or leave the version null to let it be chosen.',
            $version,
            $reason,
        ));
    }

    /**
     * The logo would cover a finder pattern.
     */
    public static function logoOverlapsFinder(): self
    {
        return new self(
            'The logo overlaps a finder pattern, which would make the code unreadable. '
            .'Reduce logo_size or logo_padding.',
        );
    }

    /**
     * The encoded matrix is not square.
     */
    public static function notSquare(int $width, int $height): self
    {
        return new self(sprintf('The encoded matrix is %dx%d; a QR symbol must be square.', $width, $height));
    }

    /**
     * The content to encode is empty.
     */
    public static function emptyContent(): self
    {
        return new self('There is nothing to encode. Pass the content to Qr::make(), or set it with ->content().');
    }

    /**
     * The format cannot be worked out from the path.
     */
    public static function indeterminateFormat(string $path): self
    {
        return new self(sprintf(
            'Cannot tell which format to write from [%s]. End the path with .svg, .png, .pdf or .eps.',
            $path,
        ));
    }

    /**
     * Writing the rendered code failed.
     */
    public static function writeFailed(string $path, ?string $disk = null): self
    {
        return new self(sprintf(
            'Could not write the QR code to [%s]%s.',
            $path,
            $disk === null ? '' : sprintf(' on disk [%s]', $disk),
        ));
    }

    /**
     * The requested renderer cannot run here.
     */
    public static function unsupportedFormat(string $format, string $hint): self
    {
        return new self(sprintf('Cannot render [%s] on this system. %s', $format, $hint));
    }

    /**
     * The named profile is not in the configuration.
     *
     * @param  array<int, string>  $known
     */
    public static function unknownProfile(string $name, array $known): self
    {
        return new self(sprintf(
            'Unknown QR profile [%s]. Defined profiles: %s.',
            $name,
            $known === [] ? 'none' : implode(', ', $known),
        ));
    }

    /**
     * The builder has no such method and no macro answers to the name.
     */
    public static function unknownMethod(string $method): self
    {
        return new self(sprintf(
            'The QR builder has no method [%s] and no macro is registered for it.',
            $method,
        ));
    }

    /**
     * A macro returned nothing, which silently breaks the chain.
     */
    public static function macroMustReturn(string $method): self
    {
        return new self(sprintf(
            'Macro [%s] returned null. A builder macro must return a value, usually $this->with([...]).',
            $method,
        ));
    }

    /**
     * Build a free-form exception.
     */
    public static function because(string $message): self
    {
        return new self($message);
    }

    /**
     * Find the closest known key, for the "did you mean" hint.
     *
     * @param  array<int, string>  $known
     */
    private static function closest(string $key, array $known): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($known as $candidate) {
            $distance = levenshtein($key, $candidate);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $candidate;
            }
        }

        return $bestDistance <= 3 ? $best : null;
    }

    /**
     * Render a value for a message without tripping over arrays or objects.
     */
    private static function stringify(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            is_scalar($value) => (string) $value,
            is_array($value) => 'array',
            default => get_debug_type($value),
        };
    }
}
