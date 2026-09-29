<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit;

use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Support\Cast;
use Gabrielesbaiz\QrcodeToolkit\Support\Color;

/**
 * A border drawn around the whole code.
 */
final readonly class Frame
{
    public function __construct(
        public string $color = '#000000',
        public float $width = 1.0,
        public float $radius = 0.0,
    ) {}

    /**
     * Build from the configuration array.
     *
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        $known = ['color', 'width', 'radius'];

        foreach (array_keys($values) as $key) {
            if (! in_array($key, $known, true)) {
                throw QrException::unknownOption('frame.'.$key, $known);
            }
        }

        $width = isset($values['width']) ? Cast::float($values['width'], 'frame.width', 0.01, 8.0) : 1.0;

        return new self(
            Color::normalize('frame.color', $values['color'] ?? '#000000'),
            $width,
            isset($values['radius']) ? Cast::float($values['radius'], 'frame.radius', 0.0, 32.0) : 0.0,
        );
    }

    /**
     * Get the frame for serialisation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'color' => $this->color,
            'width' => $this->width,
            'radius' => $this->radius,
        ];
    }
}
