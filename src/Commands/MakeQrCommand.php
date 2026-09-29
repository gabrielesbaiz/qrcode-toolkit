<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Commands;

use Gabrielesbaiz\QrcodeToolkit\Commands\Concerns\BuildsFromOptions;
use Gabrielesbaiz\QrcodeToolkit\Enums\Format;
use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Illuminate\Console\Command;

/**
 * Render one code to a file, or to standard output.
 */
class MakeQrCommand extends Command
{
    use BuildsFromOptions;

    /**
     * The console command description.
     */
    protected $description = 'Render a QR code to a file, or to standard output';

    /**
     * Build the signature, styling flags included.
     */
    public function __construct()
    {
        $this->signature = 'qr:make
            {content : The text, URL or payload to encode}
            {--o|out= : Where to write it; the extension chooses the format}
            {--format= : svg, png, pdf or eps, when there is no extension to read}
            {--disk= : Write through a filesystem disk instead of the local path}
            '.self::styleFlags();

        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $content = (string) $this->argument('content');
        $out = $this->option('out');
        $out = is_string($out) && $out !== '' ? $out : null;

        try {
            $builder = $this->builderFor($content);

            if ($out === null) {
                $format = $this->formatOption() ?? Format::Svg;

                if ($format->isBinary()) {
                    $this->components->error(sprintf(
                        'Cannot write %s to the terminal. Pass --out to choose a file.',
                        strtoupper($format->value),
                    ));

                    return self::FAILURE;
                }

                $this->line($builder->render($format));

                return self::SUCCESS;
            }

            $format = $this->formatOption() ?? Format::fromExtension(pathinfo($out, PATHINFO_EXTENSION));

            if (! $format instanceof Format) {
                throw QrException::indeterminateFormat($out);
            }

            if (! str_ends_with(strtolower($out), '.'.$format->extension())) {
                $out .= '.'.$format->extension();
            }

            $disk = $this->option('disk');
            $disk = is_string($disk) && $disk !== '' ? $disk : null;

            $builder->save($out, $disk);

            $report = $builder->scannability();

            $this->components->info(sprintf('Wrote %s', $out));
            $this->components->twoColumnDetail('Format', strtoupper($format->value));
            $this->components->twoColumnDetail('Symbol', sprintf('version %d · %dx%d', $report->version, $report->matrixSize, $report->matrixSize));
            $this->components->twoColumnDetail('Verdict', $this->tint($report->verdict()));

            return self::SUCCESS;
        } catch (QrException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Read the explicit format flag, if one was given.
     */
    protected function formatOption(): ?Format
    {
        $format = $this->option('format');

        if (! is_string($format) || $format === '') {
            return null;
        }

        return Format::tryFrom(strtolower($format))
            ?? throw QrException::invalidOption('format', $format, array_column(Format::cases(), 'value'));
    }

    /**
     * Colour a verdict for the terminal.
     */
    protected function tint(string $verdict): string
    {
        return match ($verdict) {
            'good' => '<fg=green>good</>',
            'marginal' => '<fg=yellow>marginal</>',
            default => '<fg=red>risky</>',
        };
    }
}
