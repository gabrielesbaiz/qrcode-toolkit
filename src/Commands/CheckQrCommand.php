<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Commands;

use Gabrielesbaiz\QrcodeToolkit\Commands\Concerns\BuildsFromOptions;
use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Scannability;
use Illuminate\Console\Command;

/**
 * Report what a styling choice costs in readability, and fail a build over it.
 */
class CheckQrCommand extends Command
{
    use BuildsFromOptions;

    /**
     * How serious each verdict is, worst last.
     *
     * @var array<string, int>
     */
    private const SEVERITY = ['good' => 0, 'marginal' => 1, 'risky' => 2];

    /**
     * The console command description.
     */
    protected $description = 'Report how readable a styled QR code will be, and fail if it is too risky';

    /**
     * Build the signature, styling flags included.
     */
    public function __construct()
    {
        $this->signature = 'qr:check
            {content : The text, URL or payload to encode}
            {--fail-on=risky : Exit non-zero at this verdict or worse: marginal or risky}
            {--print-width= : Report the module size at this printed width, in millimetres}
            {--json : Print the report as JSON}
            '.self::styleFlags();

        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $failOn = strtolower((string) $this->option('fail-on'));

        if (! array_key_exists($failOn, self::SEVERITY)) {
            $this->components->error(sprintf('--fail-on takes good, marginal or risky. Got [%s].', $failOn));

            return self::FAILURE;
        }

        try {
            $report = $this->builderFor((string) $this->argument('content'))->scannability();
        } catch (QrException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json') === true) {
            $this->line((string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['', ''], $this->rows($report));
        }

        $verdict = $report->verdict();

        if (self::SEVERITY[$verdict] >= self::SEVERITY[$failOn]) {
            $this->newLine();
            $this->components->error(sprintf('Verdict is %s, and --fail-on is %s.', $verdict, $failOn));

            foreach ($report->reasons() as $reason) {
                $this->components->bulletList([$reason]);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info(sprintf('Verdict: %s', $verdict));

        return self::SUCCESS;
    }

    /**
     * Build the report rows.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function rows(Scannability $report): array
    {
        $rows = [
            ['Verdict', $this->tint($report->verdict())],
            ['Error correction', $report->ecc->value.($report->eccRaised ? ' (raised for the logo)' : '')],
            ['Symbol', sprintf('version %d · %dx%d modules', $report->version, $report->matrixSize, $report->matrixSize)],
            ['Contrast', sprintf('%.2f:1', $report->contrastRatio)],
            ['Module scale', sprintf('%.2f', $report->moduleScale)],
            ['Modules erased', sprintf('%d of %d', $report->modulesErased, $report->modulesTotal)],
            ['Budget remaining', sprintf('%.1f%%', $report->budgetRemaining() * 100)],
            ['Minimum print width', sprintf('%.1f mm', $report->minimumPrintWidthMm())],
        ];

        $width = $this->option('print-width');

        if (is_numeric($width)) {
            $rows[] = ['Module at '.$width.' mm', sprintf('%.2f mm', $report->moduleSizeMm((float) $width))];
        }

        foreach ($report->reasons() as $index => $reason) {
            $rows[] = [$index === 0 ? 'Notes' : '', $reason];
        }

        return $rows;
    }

    /**
     * Colour a verdict for the terminal.
     */
    protected function tint(string $verdict): string
    {
        return match ($verdict) {
            'good' => '<fg=green;options=bold>good</>',
            'marginal' => '<fg=yellow;options=bold>marginal</>',
            default => '<fg=red;options=bold>risky</>',
        };
    }
}
