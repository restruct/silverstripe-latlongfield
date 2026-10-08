<?php

namespace Restruct\LatLong\Tests\Stub;

/**
 * Stands in for PHP's https:// stream wrapper, so LatLongField::GeoCode() can be tested without a
 * network call. It replaces the HTTP layer underneath file_get_contents() rather than a method of
 * the field, so the tests exercise the real request code (URL, stream context) unchanged.
 *
 * Set $body to the response the "server" sends, or to null to make the connection fail the way
 * an unreachable host does (file_get_contents() then warns and returns false). Every request is
 * recorded in $requests with its URL and the stream context options it was made with.
 *
 * A plain class, not a DataObject: safe in a consumer's test manifest.
 */
class HttpsStreamStub
{
    /** @var string|null response body; null = the connection fails */
    public static ?string $body = null;

    /** @var array<int, array{url: string, options: array}> */
    public static array $requests = [];

    /** @var bool whether https was registered before install(), so uninstall() can restore it */
    private static bool $hadHttps = false;

    /** @var resource|null set by PHP to the stream context of the call */
    public $context;

    private string $data = '';

    private int $position = 0;

    public static function install(?string $body): void
    {
        self::$body = $body;
        self::$requests = [];
        self::$hadHttps = in_array('https', stream_get_wrappers(), true);
        if (self::$hadHttps) {
            stream_wrapper_unregister('https');
        }
        stream_wrapper_register('https', self::class);
    }

    public static function uninstall(): void
    {
        stream_wrapper_unregister('https');
        # Only a built-in wrapper can be restored; restoring one that never existed warns
        if (self::$hadHttps) {
            stream_wrapper_restore('https');
        }
    }

    public function stream_open($path, $mode, $options, &$openedPath): bool
    {
        self::$requests[] = [
            'url' => $path,
            'options' => is_resource($this->context) ? stream_context_get_options($this->context) : [],
        ];
        if (self::$body === null) {
            return false;
        }
        $this->data = self::$body;
        $this->position = 0;
        return true;
    }

    public function stream_read($count): string
    {
        $chunk = substr($this->data, $this->position, $count);
        $this->position += strlen($chunk);
        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen($this->data);
    }

    public function stream_stat(): array
    {
        return [];
    }

    public function stream_set_option($option, $arg1, $arg2): bool
    {
        return false;
    }
}
