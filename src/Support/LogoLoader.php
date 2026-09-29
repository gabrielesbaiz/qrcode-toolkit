<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Support;

use finfo;
use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;

/**
 * The gate every logo passes through.
 *
 * A logo is the one place this package reads bytes the developer did not
 * write, so it is the one place that needs checking. Remote URLs are
 * refused in every configuration: fetching one would turn each render into
 * a server-side request forgery primitive, and would let whoever controls
 * that host change printed material after it was signed off.
 */
final readonly class LogoLoader
{
    /**
     * @param  array<string, mixed>  $security
     */
    public function __construct(private array $security = []) {}

    /**
     * Load a logo, or nothing when none was set.
     */
    public function load(?string $logo): ?LoadedLogo
    {
        if ($logo === null) {
            return null;
        }

        $value = trim($logo);

        if ($value === '') {
            throw QrException::because('The logo path is empty.');
        }

        if (str_contains($value, "\0")) {
            throw QrException::because('The logo path contains a null byte.');
        }

        [$bytes, $declaredMime] = str_starts_with(strtolower($value), 'data:')
            ? $this->fromDataUri($value)
            : $this->fromPath($value);

        [$mime, $width, $height] = $this->assertImage($bytes, $declaredMime);

        return new LoadedLogo($bytes, $mime, $width, $height, $this->fingerprint($value) ?? sha1($bytes));
    }

    /**
     * Get a cheap identity for the cache key, without reading the file.
     */
    public function fingerprint(?string $logo): ?string
    {
        if ($logo === null) {
            return null;
        }

        $value = trim($logo);

        if (str_starts_with(strtolower($value), 'data:')) {
            return sha1($value);
        }

        $real = @realpath($value);

        if ($real === false) {
            return sha1($value);
        }

        // Path, size and modification time together mean that replacing the
        // logo file invalidates every cached code that used it.
        return sha1($real.'|'.@filesize($real).'|'.@filemtime($real));
    }

    /**
     * Read a logo from the filesystem.
     *
     * @return array{0: string, 1: string|null}
     */
    private function fromPath(string $path): array
    {
        // The scheme is rejected before any filesystem call. This single
        // rule blocks http, https and ftp, and also every PHP stream
        // wrapper: phar is an object injection vector and php://filter an
        // arbitrary file read, and both matter more than the URLs do.
        if (preg_match('/^([a-zA-Z][a-zA-Z0-9+.\-]*):/', $path, $matches) === 1 && strlen($matches[1]) > 1) {
            throw QrException::because(sprintf(
                'Logos load from a local file or a data: URI only. The scheme [%s] is not allowed; '
                .'download the image and reference it locally.',
                $matches[1],
            ));
        }

        $real = realpath($path);

        if ($real === false || ! is_file($real)) {
            throw QrException::because(sprintf('The logo [%s] does not exist.', $path));
        }

        $this->assertInsideAllowedPaths($real);

        if (! is_readable($real)) {
            throw QrException::because(sprintf('The logo [%s] is not readable.', $path));
        }

        $extension = strtolower(pathinfo($real, PATHINFO_EXTENSION));

        if (! in_array($extension, $this->extensions(), true)) {
            throw QrException::because(sprintf(
                'A logo must be one of: %s. Got [%s].',
                implode(', ', $this->extensions()),
                $extension === '' ? 'no extension' : $extension,
            ));
        }

        $max = $this->maxBytes();
        $size = filesize($real);

        if ($size === false || $size === 0) {
            throw QrException::because(sprintf('The logo [%s] is empty.', $path));
        }

        if ($size > $max) {
            throw QrException::because($this->tooLarge($size, $max));
        }

        // Reading one byte past the limit closes the window between the
        // stat above and the read here.
        $bytes = file_get_contents($real, false, null, 0, $max + 1);

        if ($bytes === false) {
            throw QrException::because(sprintf('The logo [%s] could not be read.', $path));
        }

        if (strlen($bytes) > $max) {
            throw QrException::because($this->tooLarge(strlen($bytes), $max));
        }

        return [$bytes, $this->mimeForExtension($extension)];
    }

    /**
     * Read a logo from a data URI.
     *
     * @return array{0: string, 1: string|null}
     */
    private function fromDataUri(string $uri): array
    {
        $pattern = '/^data:(?<mime>[a-z]+\/[a-z0-9.+\-]+)?(?<params>(?:;[a-z0-9\-]+=[^;,]*)*);base64,(?<data>[A-Za-z0-9+\/\r\n]+={0,2})$/i';

        if (preg_match($pattern, $uri, $matches) !== 1) {
            throw QrException::because(
                'The logo data URI is malformed. Only base64 encoded data URIs are supported.',
            );
        }

        $mime = strtolower($matches['mime']);

        if ($mime === '' || ! in_array($mime, $this->mimes(), true)) {
            throw QrException::because(sprintf(
                'The logo data URI declares [%s], which is not an allowed image type.',
                $mime === '' ? 'no type' : $mime,
            ));
        }

        $data = preg_replace('/\s+/', '', $matches['data']) ?? '';
        $max = $this->maxBytes();

        // The decoded size is implied by the encoded length, so an
        // oversized payload is refused before it is ever materialised.
        if ((int) (strlen($data) * 3 / 4) > $max) {
            throw QrException::because($this->tooLarge((int) (strlen($data) * 3 / 4), $max));
        }

        $bytes = base64_decode($data, true);

        if ($bytes === false) {
            throw QrException::because('The logo data URI contains invalid base64.');
        }

        if (strlen($bytes) > $max) {
            throw QrException::because($this->tooLarge(strlen($bytes), $max));
        }

        return [$bytes, $mime];
    }

    /**
     * Check that the bytes really are the image they claim to be.
     *
     * @return array{0: string, 1: int, 2: int}
     */
    private function assertImage(string $bytes, ?string $declaredMime): array
    {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        if ($mime === false) {
            throw QrException::because('The logo content type could not be determined.');
        }

        $mime = strtolower($mime);

        // Routed on the declared type as well as the sniffed one. An SVG
        // whose first bytes are a doctype or an entity sniffs as plain
        // text, and it is the SVG checks that have something useful to say
        // about it.
        if ($mime === 'image/svg+xml' || str_contains($mime, 'svg') || $declaredMime === 'image/svg+xml') {
            return $this->assertSvg($bytes);
        }

        if (! in_array($mime, $this->mimes(), true)) {
            throw QrException::because(sprintf(
                'The logo\'s real content type is [%s], which is not an allowed image type. '
                .'A file extension is not evidence of its contents.',
                $mime,
            ));
        }

        // A mismatch between what the file says it is and what it is, is a
        // strong signal of a polyglot file, so it is refused rather than
        // quietly accepted.
        if ($declaredMime !== null && $declaredMime !== $mime) {
            throw QrException::because(sprintf(
                'The logo is declared as [%s] but its bytes are [%s].',
                $declaredMime,
                $mime,
            ));
        }

        $info = @getimagesizefromstring($bytes);

        if ($info === false || $info[0] <= 0 || $info[1] <= 0) {
            throw QrException::because('The logo could not be decoded as an image.');
        }

        $limit = $this->maxDimension();

        // A small file can declare enormous dimensions; decoding it is what
        // exhausts the memory, so the guard is on the declared size.
        if ($info[0] > $limit || $info[1] > $limit || $limit * $limit < $info[0] * $info[1]) {
            throw QrException::because(sprintf(
                'The logo is %dx%d pixels; the maximum is %dx%d.',
                $info[0],
                $info[1],
                $limit,
                $limit,
            ));
        }

        return [$mime, $info[0], $info[1]];
    }

    /**
     * Check an SVG logo, when they are allowed at all.
     *
     * @return array{0: string, 1: int, 2: int}
     */
    private function assertSvg(string $bytes): array
    {
        if (($this->security['allow_svg_logo'] ?? false) !== true) {
            throw QrException::because(
                'SVG logos are disabled. An SVG is a document that can carry script and external references, '
                .'and it is embedded verbatim into your output. Use a PNG, or set '
                .'qrcode-toolkit.security.allow_svg_logo to true if you control the file.',
            );
        }

        foreach (['<!DOCTYPE', '<!ENTITY'] as $forbidden) {
            if (stripos($bytes, $forbidden) !== false) {
                throw QrException::because(sprintf(
                    'The logo SVG contains %s, which is not allowed: it is how XML external entity attacks start.',
                    $forbidden,
                ));
            }
        }

        foreach (['<script', '<foreignObject', 'javascript:', 'data:text/html', 'onload=', 'onerror='] as $forbidden) {
            if (stripos($bytes, $forbidden) !== false) {
                throw QrException::because(sprintf('The logo SVG contains disallowed content: %s.', $forbidden));
            }
        }

        return ['image/svg+xml', 0, 0];
    }

    /**
     * Refuse a path outside the configured directories.
     */
    private function assertInsideAllowedPaths(string $real): void
    {
        /** @var array<int, string> $allowed */
        $allowed = $this->security['logo_paths'] ?? [];

        if ($allowed === []) {
            return;
        }

        foreach ($allowed as $directory) {
            $base = realpath($directory);

            if ($base !== false && str_starts_with($real, rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                return;
            }
        }

        throw QrException::because(sprintf('The logo [%s] is outside the allowed logo directories.', $real));
    }

    /**
     * Get the allowed file extensions.
     *
     * @return array<int, string>
     */
    private function extensions(): array
    {
        /** @var array<int, string> $extensions */
        $extensions = $this->security['logo_extensions'] ?? ['png', 'jpg', 'jpeg', 'gif', 'webp'];

        if (($this->security['allow_svg_logo'] ?? false) === true) {
            $extensions[] = 'svg';
        }

        return $extensions;
    }

    /**
     * Get the allowed media types.
     *
     * @return array<int, string>
     */
    private function mimes(): array
    {
        /** @var array<int, string> $mimes */
        $mimes = $this->security['logo_mimes'] ?? ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

        return $mimes;
    }

    /**
     * Get the largest logo accepted, in bytes.
     */
    private function maxBytes(): int
    {
        $value = $this->security['logo_max_bytes'] ?? null;

        return is_numeric($value) ? (int) $value : 1024 * 1024;
    }

    /**
     * Get the largest logo accepted, in pixels per side.
     */
    private function maxDimension(): int
    {
        $value = $this->security['logo_max_dimension'] ?? null;

        return is_numeric($value) ? (int) $value : 4000;
    }

    /**
     * Get the media type an extension implies.
     */
    private function mimeForExtension(string $extension): ?string
    {
        return match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => null,
        };
    }

    /**
     * Build the oversize message.
     */
    private function tooLarge(int $size, int $max): string
    {
        return sprintf(
            'The logo is %s; the maximum is %s.',
            $this->humanBytes($size),
            $this->humanBytes($max),
        );
    }

    /**
     * Render a byte count for a message.
     */
    private function humanBytes(int $bytes): string
    {
        return $bytes >= 1048576
            ? sprintf('%.1f MB', $bytes / 1048576)
            : sprintf('%.0f KB', $bytes / 1024);
    }
}
