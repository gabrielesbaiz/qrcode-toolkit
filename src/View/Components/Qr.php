<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\View\Components;

use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\QrBuilder;
use Gabrielesbaiz\QrcodeToolkit\QrManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Illuminate\View\ComponentAttributeBag;

/**
 * Render a QR code as inline SVG.
 *
 *   <x-qr data="https://laravel.com" />
 *   <x-qr :data="$order->url" profile="ticket" size="240" label="{{ $order->code }}" />
 *   <x-qr :data="$url" modules="dots" gradient-from="#4338ca" gradient-to="#be185d" frame />
 *
 * Attributes map onto configuration keys one for one; nested keys use a
 * dash, so gradient-from writes gradient.from and frame-radius writes
 * frame.radius. Anything not in the map is left on the wrapper element
 * rather than being silently swallowed.
 */
class Qr extends Component
{
    /**
     * Component attribute to dotted option key.
     *
     * @var array<string, string>
     */
    protected const OPTION_MAP = [
        'size' => 'size',
        'margin' => 'margin',
        'ecc' => 'ecc',
        'error-correction' => 'ecc',
        'version' => 'version',
        'color' => 'foreground',
        'foreground' => 'foreground',
        'background' => 'background',
        'modules' => 'modules',
        'module-scale' => 'module_scale',
        'eyes' => 'eyes',
        'eye-color' => 'eye_color',
        'eye-inner-color' => 'eye_inner_color',
        'gradient-from' => 'gradient.from',
        'gradient-to' => 'gradient.to',
        'gradient-type' => 'gradient.type',
        'gradient-angle' => 'gradient.angle',
        'logo' => 'logo',
        'logo-size' => 'logo_size',
        'logo-padding' => 'logo_padding',
        'logo-background' => 'logo_background',
        'label' => 'label',
        'label-size' => 'label_size',
        'label-color' => 'label_color',
        'label-font' => 'label_font',
        'frame-color' => 'frame.color',
        'frame-width' => 'frame.width',
        'frame-radius' => 'frame.radius',
    ];

    /**
     * Keys whose values are integers.
     */
    protected const INTEGERS = ['size', 'margin', 'version', 'label_size'];

    /**
     * Keys whose values are floats.
     */
    protected const FLOATS = ['module_scale', 'gradient.angle', 'logo_size', 'logo_padding', 'frame.width', 'frame.radius'];

    /**
     * Create a new component instance.
     *
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public ?string $data = null,
        public ?string $profile = null,
        public ?string $alt = null,
        public ?string $title = null,
        public bool $bare = false,
        public bool $img = false,
        public bool $transparent = false,
        public bool $frame = false,
        public ?string $format = null,
        public array $options = [],
    ) {}

    /**
     * Build the configured builder.
     */
    public function builder(): QrBuilder
    {
        $manager = app(QrManager::class);

        $builder = $this->profile === null
            ? $manager->make($this->payload())
            : $manager->profile($this->profile, $this->payload());

        $overrides = $this->overrides();

        return $overrides === [] ? $builder : $builder->with($overrides);
    }

    /**
     * Render the code.
     */
    public function markup(): HtmlString
    {
        $builder = $this->builder();

        if ($this->img) {
            return $builder->toImg(array_filter([
                'alt' => $this->alt ?? '',
                'title' => $this->title,
            ], static fn (mixed $value): bool => $value !== null), $this->format);
        }

        return new HtmlString($this->decorate($builder->toSvg()));
    }

    /**
     * Get the attributes that are not QR options, for the wrapper.
     */
    public function wrapperAttributes(): ComponentAttributeBag
    {
        return $this->attributes->except(array_merge(
            array_keys(self::OPTION_MAP),
            ['data', 'content', 'profile', 'alt', 'title', 'bare', 'img', 'transparent', 'frame', 'format', 'options'],
        ));
    }

    /**
     * Get the view that represents the component.
     */
    public function render(): View
    {
        /** @var view-string $view */
        $view = 'qr::components.qr';

        return view($view);
    }

    /**
     * Collect the option overrides declared as attributes.
     *
     * @return array<string, mixed>
     */
    protected function overrides(): array
    {
        /** @var array<string, mixed> $overrides */
        $overrides = [];

        foreach (self::OPTION_MAP as $attribute => $key) {
            $value = $this->attributes->get($attribute);

            if ($value === null || $value === false) {
                continue;
            }

            $overrides = $this->put($overrides, $key, $this->cast($key, $value));
        }

        if ($this->transparent) {
            $overrides['background'] = null;
        }

        // A bare <x-qr frame /> means the default frame; the frame-*
        // attributes fill in the rest of it.
        if ($this->frame && ! array_key_exists('frame', $overrides)) {
            $overrides['frame'] = [];
        }

        /** @var array<string, mixed> $merged */
        $merged = array_replace_recursive($overrides, $this->options);

        return $merged;
    }

    /**
     * Write a possibly dotted key into an options array.
     *
     * data_set() would do this, but it takes its target by reference and
     * hands back a value static analysis cannot follow, and the nesting
     * here is only ever one level deep.
     *
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    protected function put(array $target, string $key, mixed $value): array
    {
        if (! str_contains($key, '.')) {
            $target[$key] = $value;

            return $target;
        }

        [$outer, $inner] = explode('.', $key, 2);

        $nested = $target[$outer] ?? [];

        if (! is_array($nested)) {
            $nested = [];
        }

        $nested[$inner] = $value;
        $target[$outer] = $nested;

        return $target;
    }

    /**
     * Cast an attribute to the type its option expects.
     */
    protected function cast(string $key, mixed $value): mixed
    {
        if (in_array($key, self::INTEGERS, true)) {
            return is_numeric($value) ? (int) $value : $value;
        }

        if (in_array($key, self::FLOATS, true)) {
            return is_numeric($value) ? (float) $value : $value;
        }

        return is_string($value) ? trim($value) : $value;
    }

    /**
     * Get the payload to encode.
     */
    protected function payload(): string
    {
        $content = $this->data ?? $this->attributes->get('content');
        $data = is_scalar($content) ? trim((string) $content) : '';

        if ($data === '') {
            throw QrException::because('An <x-qr> component needs a data attribute to encode.');
        }

        return $data;
    }

    /**
     * Add the accessibility hooks the renderer cannot know about.
     *
     * Escaped here, because they are injected after the renderer has
     * finished with the document.
     */
    protected function decorate(string $svg): string
    {
        $label = $this->alt ?? $this->title;

        if ($label === null || $label === '') {
            return str_replace('<svg ', '<svg role="presentation" aria-hidden="true" ', $svg);
        }

        $escaped = htmlspecialchars($label, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');

        $svg = str_replace('<svg ', '<svg role="img" aria-label="'.$escaped.'" ', $svg);

        return preg_replace('/(<svg\b[^>]*>)/', '$1<title>'.$escaped.'</title>', $svg, 1) ?? $svg;
    }
}
