<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit;

use Gabrielesbaiz\QrcodeToolkit\Enums\Ecc;

/**
 * What a rendered code costs itself in readability.
 *
 * Styling a QR code is a trade against the error correction budget, and
 * nothing else in the ecosystem tells you how much of that budget you have
 * spent. This is the report that makes an aggressive style defensible
 * rather than merely hopeful.
 */
final readonly class Scannability
{
    /**
     * Below this, phone cameras start failing on printed codes.
     */
    public const MIN_MODULE_MM = 0.4;

    /**
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public Ecc $ecc,
        public bool $eccRaised,
        public int $version,
        public int $matrixSize,
        public int $modulesTotal,
        public int $modulesErased,
        public float $contrastRatio,
        public float $moduleScale,
        public bool $transparentBackground,
        public array $warnings = [],
    ) {}

    /**
     * Get the share of the correction budget the logo has spent, 0 to 1.
     */
    public function budgetUsed(): float
    {
        if ($this->modulesTotal === 0) {
            return 0.0;
        }

        $erasedShare = $this->modulesErased / $this->modulesTotal;

        return min(1.0, $erasedShare / $this->ecc->tolerance());
    }

    /**
     * Get the share of the correction budget still in hand, 0 to 1.
     */
    public function budgetRemaining(): float
    {
        return 1.0 - $this->budgetUsed();
    }

    /**
     * Get the physical size of one module at a given printed width.
     */
    public function moduleSizeMm(float $printedWidthMm): float
    {
        $modulesAcross = $this->matrixSize;

        return $modulesAcross === 0 ? 0.0 : $printedWidthMm / $modulesAcross;
    }

    /**
     * Get the smallest width this code should be printed at.
     */
    public function minimumPrintWidthMm(): float
    {
        return $this->matrixSize * self::MIN_MODULE_MM;
    }

    /**
     * Get the overall judgement.
     *
     * The thresholds are calibrated against the scan-back suite: a style
     * this calls risky is one that has actually failed to decode, not one
     * that merely looks busy.
     */
    public function verdict(): string
    {
        if ($this->contrastRatio < 3.0 || $this->budgetUsed() > 0.9 || $this->moduleScale < 0.45) {
            return 'risky';
        }

        if ($this->contrastRatio < 4.5 || $this->budgetUsed() > 0.6 || $this->moduleScale < 0.65) {
            return 'marginal';
        }

        return 'good';
    }

    /**
     * Get the reasons behind the verdict, worst first.
     *
     * @return array<int, string>
     */
    public function reasons(): array
    {
        $reasons = [];

        if ($this->contrastRatio < 4.5) {
            $reasons[] = sprintf('Contrast is %.2f:1; 4.5:1 or better reads reliably in poor light.', $this->contrastRatio);
        }

        if ($this->budgetUsed() > 0.6) {
            $reasons[] = sprintf(
                'The logo has used %.0f%% of the %s correction budget, erasing %d of %d modules.',
                $this->budgetUsed() * 100,
                $this->ecc->value,
                $this->modulesErased,
                $this->modulesTotal,
            );
        }

        if ($this->moduleScale < 0.65) {
            $reasons[] = sprintf('A module scale of %.2f leaves little ink for a camera to find.', $this->moduleScale);
        }

        if ($this->transparentBackground) {
            $reasons[] = 'The background is transparent, so contrast was measured against white. Check the surface it lands on.';
        }

        return [...$reasons, ...$this->warnings];
    }

    /**
     * Get the report as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'verdict' => $this->verdict(),
            'ecc' => $this->ecc->value,
            'ecc_raised' => $this->eccRaised,
            'version' => $this->version,
            'matrix_size' => $this->matrixSize,
            'modules_total' => $this->modulesTotal,
            'modules_erased' => $this->modulesErased,
            'budget_used' => round($this->budgetUsed(), 4),
            'budget_remaining' => round($this->budgetRemaining(), 4),
            'contrast_ratio' => round($this->contrastRatio, 2),
            'module_scale' => $this->moduleScale,
            'transparent_background' => $this->transparentBackground,
            'minimum_print_width_mm' => round($this->minimumPrintWidthMm(), 1),
            'reasons' => $this->reasons(),
        ];
    }
}
