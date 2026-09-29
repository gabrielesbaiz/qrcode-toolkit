<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Facades;

use Gabrielesbaiz\QrcodeToolkit\QrBuilder;
use Gabrielesbaiz\QrcodeToolkit\QrManager;
use Illuminate\Support\Facades\Facade;

/**
 * The QR code toolkit.
 *
 * Every method returns an immutable builder. Nothing is encoded or drawn
 * until you ask for bytes with toSvg(), toPng(), response(), download() or
 * save().
 *
 * @method static QrBuilder make(string $content = '')
 * @method static QrBuilder profile(string $name, string $content = '')
 * @method static QrBuilder url(string $url)
 * @method static QrBuilder wifi(string $ssid, string|null $password = null, string $encryption = 'WPA', bool $hidden = false, array<string, string> $enterprise = [])
 * @method static QrBuilder vcard(array<string, mixed> $fields, bool $mecard = false)
 * @method static QrBuilder email(string $to, string|null $subject = null, string|null $body = null, bool $matmsg = false)
 * @method static QrBuilder phone(string $number)
 * @method static QrBuilder sms(string $number, string|null $message = null)
 * @method static QrBuilder geo(float $latitude, float $longitude, float|null $altitude = null, string|null $query = null)
 * @method static QrBuilder epc(string $name, string $iban, float|string|null $amount = null, string|null $remittance = null, string|null $bic = null, string|null $purpose = null, string|null $reference = null, string|null $information = null)
 * @method static array<string, mixed> defaults()
 * @method static array<int, string> profiles()
 *
 * @see QrManager
 * @see QrBuilder
 */
class Qr extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return QrManager::class;
    }
}
