<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Enums;

use BaconQrCode\Common\ErrorCorrectionLevel;

/**
 * The four error correction levels defined by the QR specification.
 */
enum Ecc: string
{
    case L = 'L';
    case M = 'M';
    case Q = 'Q';
    case H = 'H';

    /**
     * Get the ordering rank, lowest correction first.
     */
    public function rank(): int
    {
        return match ($this) {
            self::L => 0,
            self::M => 1,
            self::Q => 2,
            self::H => 3,
        };
    }

    /**
     * Get the share of the symbol that can be destroyed and still recovered.
     */
    public function tolerance(): float
    {
        return match ($this) {
            self::L => 0.07,
            self::M => 0.15,
            self::Q => 0.25,
            self::H => 0.30,
        };
    }

    /**
     * Get the corresponding Bacon level.
     *
     * Bacon predates native enums and models its levels as an AbstractEnum, so
     * this is the one place outside the encoder that names a Bacon symbol.
     */
    public function bacon(): ErrorCorrectionLevel
    {
        return match ($this) {
            self::L => ErrorCorrectionLevel::L(),
            self::M => ErrorCorrectionLevel::M(),
            self::Q => ErrorCorrectionLevel::Q(),
            self::H => ErrorCorrectionLevel::H(),
        };
    }

    /**
     * Get whichever of the two levels corrects more.
     */
    public function atLeast(self $other): self
    {
        return $this->rank() >= $other->rank() ? $this : $other;
    }
}
