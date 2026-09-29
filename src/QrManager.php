<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit;

use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Support\CacheSpec;
use Gabrielesbaiz\QrcodeToolkit\Support\Epc;
use Gabrielesbaiz\QrcodeToolkit\Support\Payload;
use Gabrielesbaiz\QrcodeToolkit\Support\RenderContext;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * The entry point behind the Qr facade.
 */
final readonly class QrManager
{
    public function __construct(
        private RenderContext $context,
        private ConfigRepository $config,
        private CacheSpec $cache,
    ) {}

    /**
     * Start a code from the configured defaults.
     */
    public function make(string $content = ''): QrBuilder
    {
        return new QrBuilder($content, Options::fromArray($this->defaults()), $this->context, $this->cache);
    }

    /**
     * Start a code from a named profile.
     */
    public function profile(string $name, string $content = ''): QrBuilder
    {
        /** @var array<string, array<string, mixed>> $profiles */
        $profiles = $this->config->get('qrcode-toolkit.profiles', []);

        if (! array_key_exists($name, $profiles)) {
            throw QrException::unknownProfile($name, array_keys($profiles));
        }

        // Recursive, so a profile can override gradient.type without
        // having to restate gradient.from alongside it.
        /** @var array<string, mixed> $options */
        $options = array_replace_recursive($this->defaults(), $profiles[$name]);

        return new QrBuilder($content, Options::fromArray($options), $this->context, $this->cache);
    }

    /**
     * Get the configured defaults.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        /** @var array<string, mixed> $defaults */
        $defaults = $this->config->get('qrcode-toolkit.defaults', []);

        // optimize_url is a manager setting rather than a drawing one, so
        // it lives alongside the defaults for discoverability but must not
        // reach the options object, which rejects keys it does not know.
        unset($defaults['optimize_url']);

        // A level that came from the config file is a default, not a
        // decision, so it must not stop a logo raising the correction. Only
        // errorCorrection() and epc() mark the choice as explicit.
        $defaults['ecc_explicit'] = false;

        $minContrast = $this->config->get('qrcode-toolkit.security.min_contrast');

        if ($minContrast !== null) {
            $defaults['min_contrast'] = $minContrast;
        }

        return $defaults;
    }

    /**
     * Get the names of the configured profiles.
     *
     * @return array<int, string>
     */
    public function profiles(): array
    {
        /** @var array<string, mixed> $profiles */
        $profiles = $this->config->get('qrcode-toolkit.profiles', []);

        return array_keys($profiles);
    }

    /**
     * Encode a link.
     */
    public function url(string $url): QrBuilder
    {
        $candidate = trim($url);

        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', $candidate) !== 1 && ! str_starts_with($candidate, 'mailto:') && ! str_starts_with($candidate, 'tel:')) {
            $candidate = 'https://'.$candidate;
        }

        if (filter_var($candidate, FILTER_VALIDATE_URL) === false) {
            throw QrException::because(sprintf('[%s] is not a valid URL.', $url));
        }

        if ($this->config->get('qrcode-toolkit.defaults.optimize_url', false) === true) {
            $candidate = $this->optimiseUrl($candidate);
        }

        return $this->make($candidate);
    }

    /**
     * Encode a wireless network.
     *
     * @param  array<string, string>  $enterprise
     */
    public function wifi(
        string $ssid,
        ?string $password = null,
        string $encryption = 'WPA',
        bool $hidden = false,
        array $enterprise = [],
    ): QrBuilder {
        return $this->make(Payload::wifi($ssid, $password, $encryption, $hidden, $enterprise));
    }

    /**
     * Encode a contact card.
     *
     * vCard 3.0 by default, because every mainstream scanner imports it
     * straight into Contacts. MECARD is offered because it is around forty
     * per cent shorter, which matters at small print sizes.
     *
     * @param  array<string, mixed>  $fields
     */
    public function vcard(array $fields, bool $mecard = false): QrBuilder
    {
        return $this->make($mecard ? Payload::mecard($fields) : Payload::vcard($fields));
    }

    /**
     * Encode an email.
     */
    public function email(string $to, ?string $subject = null, ?string $body = null, bool $matmsg = false): QrBuilder
    {
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw QrException::because(sprintf('[%s] is not a valid email address.', $to));
        }

        if ($matmsg) {
            $payload = 'MATMSG:TO:'.$this->escapeMatmsg($to).';';
            $payload .= 'SUB:'.$this->escapeMatmsg((string) $subject).';';
            $payload .= 'BODY:'.$this->escapeMatmsg((string) $body).';;';

            return $this->make($payload);
        }

        $query = [];

        if ($subject !== null && $subject !== '') {
            $query[] = 'subject='.rawurlencode($subject);
        }

        if ($body !== null && $body !== '') {
            // rawurlencode, not http_build_query: RFC 6068 wants %20 for a
            // space, and http_build_query emits a plus, which arrives in
            // the mail composer as a literal plus sign.
            $query[] = 'body='.rawurlencode($body);
        }

        return $this->make('mailto:'.$to.($query === [] ? '' : '?'.implode('&', $query)));
    }

    /**
     * Encode a telephone number.
     */
    public function phone(string $number): QrBuilder
    {
        $normalised = $this->normaliseNumber($number);

        return $this->make('tel:'.$normalised);
    }

    /**
     * Encode a text message.
     */
    public function sms(string $number, ?string $message = null): QrBuilder
    {
        $normalised = $this->normaliseNumber($number);

        // No escaping, deliberately: only the first colon after the number
        // separates, so the rest is taken literally, and escaping it here
        // is what breaks the message on real scanners.
        return $this->make('SMSTO:'.$normalised.($message === null || $message === '' ? '' : ':'.$message));
    }

    /**
     * Encode a geographic point.
     */
    public function geo(float $latitude, float $longitude, ?float $altitude = null, ?string $query = null): QrBuilder
    {
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw QrException::outOfRange('latitude', $latitude, -90, 90);
        }

        if ($longitude < -180.0 || $longitude > 180.0) {
            throw QrException::outOfRange('longitude', $longitude, -180, 180);
        }

        $payload = 'geo:'.$this->coordinate($latitude).','.$this->coordinate($longitude);

        if ($altitude !== null) {
            $payload .= ','.$this->coordinate($altitude);
        }

        if ($query !== null && $query !== '') {
            $payload .= '?q='.rawurlencode($query);
        }

        return $this->make($payload);
    }

    /**
     * Encode a SEPA credit transfer, a GiroCode.
     *
     * The specification requires error correction M and forbids anything
     * obscuring the symbol, so both are pinned here and the builder
     * refuses a logo afterwards.
     */
    public function epc(
        string $name,
        string $iban,
        float|string|null $amount = null,
        ?string $remittance = null,
        ?string $bic = null,
        ?string $purpose = null,
        ?string $reference = null,
        ?string $information = null,
    ): QrBuilder {
        $payload = Epc::build($name, $iban, $amount, $remittance, $bic, $purpose, $reference, $information);

        return $this->make($payload)->with([
            'ecc' => 'M',
            'ecc_explicit' => true,
            'payload_kind' => 'epc',
            'logo' => null,
        ]);
    }

    /**
     * Uppercase the parts of a URL that are case insensitive.
     *
     * Scheme and host are defined as case insensitive, and an all-uppercase
     * payload falls into the alphanumeric QR mode, which can drop a whole
     * symbol version. The path is never touched, because it is not.
     */
    private function optimiseUrl(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $prefix = strtoupper($parts['scheme'].'://'.$parts['host']);
        $rest = substr($url, strlen($parts['scheme'].'://'.$parts['host']));

        return $prefix.$rest;
    }

    /**
     * Reduce a phone number to digits and one leading plus.
     */
    private function normaliseNumber(string $number): string
    {
        $plus = str_starts_with(trim($number), '+');
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (strlen($digits) < 3) {
            throw QrException::because(sprintf('[%s] does not look like a telephone number.', $number));
        }

        return ($plus ? '+' : '').$digits;
    }

    /**
     * Format a coordinate, independently of the active locale.
     *
     * Under an it_IT locale sprintf('%f') emits a comma for the decimal
     * point, which silently corrupts the payload.
     */
    private function coordinate(float $value): string
    {
        $formatted = number_format($value, 6, '.', '');

        return str_contains($formatted, '.')
            ? rtrim(rtrim($formatted, '0'), '.')
            : $formatted;
    }

    /**
     * Escape a MATMSG field.
     */
    private function escapeMatmsg(string $value): string
    {
        return strtr($value, ['\\' => '\\\\', ';' => '\;', ':' => '\\:']);
    }
}
