<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Support;

use BaconQrCode\Common\Version;
use BaconQrCode\Encoder\Encoder as BaconEncoder;
use BaconQrCode\Encoder\QrCode;
use BaconQrCode\Exception\ExceptionInterface as BaconException;
use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Options;

/**
 * The single place Bacon is used.
 *
 * Bacon does the Reed-Solomon encoding, which is the one part of making a QR
 * code that should never be rewritten. Everything after this class is ours.
 */
final class Encoder
{
    /**
     * Encode the content into a module matrix.
     */
    public static function encode(string $content, Options $options): Matrix
    {
        return Matrix::fromByteMatrix(self::run($content, $options)->getMatrix());
    }

    /**
     * Encode and report the chosen version alongside the matrix.
     *
     * @return array{matrix: Matrix, version: int}
     */
    public static function encodeWithVersion(string $content, Options $options): array
    {
        $code = self::run($content, $options);

        return [
            'matrix' => Matrix::fromByteMatrix($code->getMatrix()),
            'version' => $code->getVersion()->getVersionNumber(),
        ];
    }

    /**
     * Decide whether to announce the character set with an ECI header.
     *
     * An ECI prefix is what tells a scanner the bytes are UTF-8, and it is
     * required for content that is not plain ASCII. It is also a
     * compatibility hazard: a good many scanners, including one of the
     * decoders this package tests against, fail to read a code that
     * carries one unnecessarily. So it is emitted only when the content
     * actually needs it, unless the caller says otherwise.
     */
    private static function shouldPrefixEci(string $content, Options $options): bool
    {
        if ($options->eci !== null) {
            return $options->eci;
        }

        return preg_match('/^[\x00-\x7F]*$/', $content) !== 1;
    }

    /**
     * Run Bacon's encoder, translating its failures into ours.
     */
    private static function run(string $content, Options $options): QrCode
    {
        if (trim($content) === '') {
            throw QrException::emptyContent();
        }

        $version = $options->version === null
            ? null
            : Version::getVersionForNumber($options->version);

        try {
            return BaconEncoder::encode(
                $content,
                $options->effectiveEcc()->bacon(),
                $options->encoding,
                $version,
                self::shouldPrefixEci($content, $options),
            );
        } catch (BaconException $exception) {
            if ($options->version !== null) {
                throw QrException::versionTooSmall($options->version, $exception->getMessage());
            }

            throw QrException::because('The content could not be encoded: '.$exception->getMessage());
        }
    }
}
