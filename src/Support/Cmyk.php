<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Support;

use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;

/**
 * A CMYK colour, for the print formats.
 *
 * PDF and EPS are the formats people send to a printer, and a printer wants
 * ink percentages rather than screen light. Components are held as 0..1.
 */
final readonly class Cmyk
{
    public function __construct(
        public float $c,
        public float $m,
        public float $y,
        public float $k,
    ) {}

    /**
     * Build from four components given as 0..1 or as 0..100.
     *
     * @param  array<int, float|int|string>  $components
     */
    public static function fromArray(string $key, array $components): self
    {
        if (count($components) !== 4) {
            throw QrException::because(sprintf(
                'Option [%s] must be four CMYK components, given as 0..1 or as 0..100. Got %d.',
                $key,
                count($components),
            ));
        }

        $values = [];

        foreach (array_values($components) as $index => $component) {
            if (! is_numeric($component)) {
                throw QrException::because(sprintf('Option [%s] has a non-numeric CMYK component at position %d.', $key, $index));
            }

            $values[] = (float) $component;
        }

        // Anything above 1 can only have been meant as a percentage.
        $scale = max($values) > 1.0 ? 100.0 : 1.0;

        foreach ($values as $index => $value) {
            if ($value < 0.0 || $value > $scale) {
                throw QrException::outOfRange($key, $value, 0, $scale);
            }

            $values[$index] = Num::normalize($value / $scale);
        }

        return new self($values[0], $values[1], $values[2], $values[3]);
    }

    /**
     * Convert a hex colour naively.
     *
     * This is the textbook conversion and it is right for black on white and
     * roughly right for everything else. Anyone who cares about matching a
     * brand colour should pass the values their printer gave them, and the
     * renderer records a warning when it has to fall back to this.
     */
    public static function fromHex(string $hex): self
    {
        [$r, $g, $b] = Color::unit($hex);

        $k = 1.0 - max($r, $g, $b);

        if ($k >= 1.0) {
            return new self(0.0, 0.0, 0.0, 1.0);
        }

        return new self(
            Num::normalize((1.0 - $r - $k) / (1.0 - $k)),
            Num::normalize((1.0 - $g - $k) / (1.0 - $k)),
            Num::normalize((1.0 - $b - $k) / (1.0 - $k)),
            Num::normalize($k),
        );
    }

    /**
     * Get the four components, 0..1.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public function components(): array
    {
        return [$this->c, $this->m, $this->y, $this->k];
    }

    /**
     * Get the components for serialisation.
     *
     * @return array<int, float>
     */
    public function toArray(): array
    {
        return $this->components();
    }
}
