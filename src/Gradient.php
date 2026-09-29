<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit;

use Gabrielesbaiz\QrcodeToolkit\Enums\GradientType;
use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Support\Cast;
use Gabrielesbaiz\QrcodeToolkit\Support\Color;
use Gabrielesbaiz\QrcodeToolkit\Support\Num;

/**
 * A two stop gradient across the body of the code.
 */
final readonly class Gradient
{
    public function __construct(
        public GradientType $type,
        public string $from,
        public string $to,
        public float $angle = 0.0,
    ) {}

    /**
     * Build from the configuration array.
     *
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        $known = ['type', 'from', 'to', 'angle'];

        foreach (array_keys($values) as $key) {
            if (! in_array($key, $known, true)) {
                throw QrException::unknownOption('gradient.'.$key, $known);
            }
        }

        foreach (['from', 'to'] as $required) {
            if (! isset($values[$required])) {
                throw QrException::missingKey('gradient', $required);
            }
        }

        $angle = isset($values['angle']) ? Cast::float($values['angle'], 'gradient.angle', -3600.0, 3600.0) : 0.0;

        return new self(
            Cast::enum(GradientType::class, $values['type'] ?? GradientType::Linear, 'gradient.type'),
            Color::normalize('gradient.from', $values['from']),
            Color::normalize('gradient.to', $values['to']),
            Num::normalize(fmod(fmod($angle, 360.0) + 360.0, 360.0)),
        );
    }

    /**
     * Get the stop that contrasts least with a background.
     *
     * A gradient is only as scannable as its worse end, so that is the end
     * the contrast guard measures.
     */
    public function weakestStop(string $background): string
    {
        return Color::contrast($this->from, $background) <= Color::contrast($this->to, $background)
            ? $this->from
            : $this->to;
    }

    /**
     * Get the gradient for serialisation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'from' => $this->from,
            'to' => $this->to,
            'angle' => $this->angle,
        ];
    }
}
