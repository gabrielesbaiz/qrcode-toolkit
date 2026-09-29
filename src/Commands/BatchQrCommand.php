<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Commands;

use Gabrielesbaiz\QrcodeToolkit\Commands\Concerns\BuildsFromOptions;
use Gabrielesbaiz\QrcodeToolkit\Enums\Format;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Render one code per row of a CSV.
 *
 * The bulk cases — event tickets, asset tags, table cards — are why a
 * command exists at all, and they are the ones where a single bad row
 * should not lose the other nine hundred.
 */
class BatchQrCommand extends Command
{
    use BuildsFromOptions;

    /**
     * The console command description.
     */
    protected $description = 'Render one QR code per row of a CSV file';

    /**
     * Build the signature, styling flags included.
     */
    public function __construct()
    {
        $this->signature = 'qr:batch
            {csv : Path to a CSV file with a header row}
            {--column=content : The column holding what to encode}
            {--name-column= : The column holding the output filename; defaults to the row number}
            {--out=. : Directory to write into}
            {--disk= : Write through a filesystem disk instead}
            {--format=png : svg, png, pdf or eps}
            {--check : Report the readability of each code, and fail on a risky one}
            {--stop-on-error : Stop at the first bad row rather than carrying on}
            '.self::styleFlags();

        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $csv = (string) $this->argument('csv');

        if (! is_file($csv) || ! is_readable($csv)) {
            $this->components->error(sprintf('Cannot read [%s].', $csv));

            return self::FAILURE;
        }

        $format = Format::tryFrom(strtolower((string) $this->option('format')));

        if ($format === null) {
            $this->components->error('--format takes svg, png, pdf or eps.');

            return self::FAILURE;
        }

        $handle = fopen($csv, 'rb');

        if ($handle === false) {
            $this->components->error(sprintf('Cannot open [%s].', $csv));

            return self::FAILURE;
        }

        $header = fgetcsv($handle);

        if (! is_array($header)) {
            fclose($handle);
            $this->components->error('The CSV has no header row.');

            return self::FAILURE;
        }

        /** @var array<int, string> $columns */
        $columns = array_map(static fn (mixed $c): string => trim((string) $c), $header);

        $contentColumn = (string) $this->option('column');
        $index = array_search($contentColumn, $columns, true);

        if ($index === false) {
            fclose($handle);
            $this->components->error(sprintf(
                'No column named [%s]. The header has: %s.',
                $contentColumn,
                implode(', ', $columns),
            ));

            return self::FAILURE;
        }

        $nameColumn = $this->option('name-column');
        $nameIndex = is_string($nameColumn) && $nameColumn !== ''
            ? array_search($nameColumn, $columns, true)
            : false;

        if (is_string($nameColumn) && $nameColumn !== '' && $nameIndex === false) {
            fclose($handle);
            $this->components->error(sprintf('No column named [%s].', $nameColumn));

            return self::FAILURE;
        }

        $directory = rtrim((string) $this->option('out'), '/');
        $disk = $this->option('disk');
        $disk = is_string($disk) && $disk !== '' ? $disk : null;

        $written = 0;
        $failed = 0;
        $risky = 0;
        $row = 1;

        while (($record = fgetcsv($handle)) !== false) {
            $row++;

            if ($record === [null]) {
                continue;
            }

            $content = trim($record[$index] ?? '');

            if ($content === '') {
                $this->components->warn(sprintf('Row %d has nothing to encode; skipped.', $row));

                continue;
            }

            $name = $nameIndex !== false
                ? trim($record[$nameIndex] ?? '')
                : (string) $row;

            $name = Str::of($name === '' ? (string) $row : $name)->slug('-')->value();
            $path = $directory.'/'.$name.'.'.$format->extension();

            try {
                $builder = $this->builderFor($content);

                if ($this->option('check') === true) {
                    $verdict = $builder->scannability()->verdict();

                    if ($verdict === 'risky') {
                        $risky++;
                        $this->components->warn(sprintf('Row %d (%s) is risky.', $row, $name));
                    }
                }

                $builder->save($path, $disk);
                $written++;
            } catch (Throwable $exception) {
                $failed++;
                $this->components->error(sprintf('Row %d: %s', $row, $exception->getMessage()));

                if ($this->option('stop-on-error') === true) {
                    fclose($handle);

                    return self::FAILURE;
                }
            }
        }

        fclose($handle);

        $this->newLine();
        $this->components->twoColumnDetail('Written', (string) $written);

        if ($failed > 0) {
            $this->components->twoColumnDetail('Failed', '<fg=red>'.$failed.'</>');
        }

        if ($risky > 0) {
            $this->components->twoColumnDetail('Risky', '<fg=yellow>'.$risky.'</>');
        }

        if ($failed > 0) {
            return self::FAILURE;
        }

        return $risky > 0 && $this->option('check') === true ? self::FAILURE : self::SUCCESS;
    }
}
