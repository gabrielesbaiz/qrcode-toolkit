<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Commands\Concerns;

use Gabrielesbaiz\QrcodeToolkit\QrBuilder;
use Gabrielesbaiz\QrcodeToolkit\QrManager;

/**
 * The styling flags every generating command shares.
 */
trait BuildsFromOptions
{
    /**
     * Command-line flag to option key, and how to read the value.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected array $optionFlags = [
        'size' => ['size', 'int'],
        'margin' => ['margin', 'int'],
        'ecc' => ['ecc', 'string'],
        'symbol-version' => ['version', 'int'],
        'color' => ['foreground', 'string'],
        'background' => ['background', 'string'],
        'modules' => ['modules', 'string'],
        'module-scale' => ['module_scale', 'float'],
        'eyes' => ['eyes', 'string'],
        'eye-color' => ['eye_color', 'string'],
        'eye-inner-color' => ['eye_inner_color', 'string'],
        'logo' => ['logo', 'string'],
        'logo-size' => ['logo_size', 'float'],
        'label' => ['label', 'string'],
        'label-size' => ['label_size', 'int'],
        'gradient-from' => ['gradient.from', 'string'],
        'gradient-to' => ['gradient.to', 'string'],
        'gradient-type' => ['gradient.type', 'string'],
        'gradient-angle' => ['gradient.angle', 'float'],
        'frame-color' => ['frame.color', 'string'],
        'frame-width' => ['frame.width', 'float'],
        'frame-radius' => ['frame.radius', 'float'],
    ];

    /**
     * Build a code from the flags this command was given.
     */
    protected function builderFor(string $content): QrBuilder
    {
        $manager = app(QrManager::class);

        $profile = $this->option('profile');

        $builder = is_string($profile) && $profile !== ''
            ? $manager->profile($profile, $content)
            : $manager->make($content);

        $overrides = $this->overridesFromFlags();

        return $overrides === [] ? $builder : $builder->with($overrides);
    }

    /**
     * Collect the option overrides the flags describe.
     *
     * @return array<string, mixed>
     */
    protected function overridesFromFlags(): array
    {
        /** @var array<string, mixed> $overrides */
        $overrides = [];

        foreach ($this->optionFlags as $flag => [$key, $cast]) {
            $value = $this->option($flag);

            // A repeatable option arrives as an array; none of ours is
            // repeatable, so anything but a scalar is not for us.
            if (! is_scalar($value) || $value === false || $value === '') {
                continue;
            }

            $typed = match ($cast) {
                'int' => (int) $value,
                'float' => (float) $value,
                default => (string) $value,
            };

            if (! str_contains($key, '.')) {
                $overrides[$key] = $typed;

                continue;
            }

            [$outer, $inner] = explode('.', $key, 2);
            $nested = $overrides[$outer] ?? [];
            $nested = is_array($nested) ? $nested : [];
            $nested[$inner] = $typed;
            $overrides[$outer] = $nested;
        }

        if ($this->option('transparent') === true) {
            $overrides['background'] = null;
        }

        if ($this->option('frame') === true && ! array_key_exists('frame', $overrides)) {
            $overrides['frame'] = [];
        }

        if ($this->option('no-contrast-check') === true) {
            $overrides['min_contrast'] = 0;
        }

        return $overrides;
    }

    /**
     * Get the shared styling flags, for a command signature.
     */
    protected static function styleFlags(): string
    {
        return implode("\n        ", [
            '{--profile= : Start from a named profile}',
            '{--size= : Output width in pixels}',
            '{--margin= : Quiet zone, in modules}',
            '{--ecc= : Error correction: L, M, Q or H}',
            '{--symbol-version= : Pin the symbol version, 1 to 40}',
            '{--color= : Module colour, as hex}',
            '{--background= : Background colour, as hex}',
            '{--transparent : Drop the background}',
            '{--modules= : square, rounded, dots, diamond, vertical or horizontal}',
            '{--module-scale= : How much of its cell a module fills, 0.1 to 1.0}',
            '{--eyes= : square, rounded, circle, leaf, pointy or module}',
            '{--eye-color= : Eye ring colour, as hex}',
            '{--eye-inner-color= : Eye pupil colour, as hex}',
            '{--gradient-from= : Gradient start colour}',
            '{--gradient-to= : Gradient end colour}',
            '{--gradient-type= : linear or radial}',
            '{--gradient-angle= : Gradient angle in degrees}',
            '{--logo= : A local logo path, or a data URI}',
            '{--logo-size= : Logo size as a fraction of the symbol}',
            '{--label= : Caption under the code}',
            '{--label-size= : Caption size in pixels}',
            '{--frame : Draw a frame}',
            '{--frame-color= : Frame colour, as hex}',
            '{--frame-width= : Frame width, in modules}',
            '{--frame-radius= : Frame corner radius, in modules}',
            '{--no-contrast-check : Skip the contrast guard}',
        ]);
    }
}
