<?php

namespace Restruct\LatLong\Tests;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Restruct\LatLong\Tests\Stub\HttpsStreamStub;
use Restruct\SilverStripe\Forms\LatLongField;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * LatLongField::GeoCode() against a stubbed https:// layer (see HttpsStreamStub): no request in
 * this suite leaves the machine.
 *
 * Regressions for issue #3: the request had no timeout, a network failure raised PHP warnings
 * (file_get_contents() failing, then reading 'status' off null), and without GMAPS_API_KEY the
 * method returned the bool from user_error() instead of null. The contract now is: the first
 * result as an array, or null on every failure; a failed request is logged, never a PHP warning.
 */
class GeoCodeTest extends SapphireTest
{
    /** @var array environment as it was before each test, restored in tearDown() */
    private array $envBackup = [];

    /** @var array PHP errors raised during the GeoCode() call under test */
    private array $phpErrors = [];

    /** @var TestHandler records what GeoCode() logs */
    private TestHandler $log;

    protected function setUp(): void
    {
        parent::setUp();
        $this->envBackup = Environment::getVariables();
        Environment::setEnv('GMAPS_API_KEY', 'server-key');
        Environment::setEnv('GMAPS_BROWSER_KEY', 'browser-key');
        # SapphireTest nests the Injector per test, so this replacement logger does not leak
        $this->log = new TestHandler();
        Injector::inst()->registerService(new Logger('geocode-test', [$this->log]), LoggerInterface::class);
    }

    /** @return string[] the messages logged during the test */
    private function logged(): array
    {
        return array_map(fn ($record) => (string) $record['message'], $this->log->getRecords());
    }

    protected function tearDown(): void
    {
        # Environment is process-wide and SapphireTest does not restore it
        Environment::setVariables($this->envBackup);
        parent::tearDown();
    }

    /**
     * Run GeoCode() with the https stub answering $body (null = connection failure), recording
     * every PHP error raised meanwhile instead of letting the runner's handler see it. Whether a
     * warning fails a test differs between PHPUnit 9 (SS5) and 11 (SS6); recording them makes the
     * "no warnings" assertion the same on both.
     */
    private function geoCode(string $address, ?string $body)
    {
        $this->phpErrors = [];
        HttpsStreamStub::install($body);
        set_error_handler(function (int $errno, string $errstr) {
            $this->phpErrors[] = $errstr;
            return true;
        });
        try {
            return LatLongField::GeoCode($address);
        } finally {
            restore_error_handler();
            HttpsStreamStub::uninstall();
        }
    }

    private static function okBody(): string
    {
        return json_encode([
            'status' => 'OK',
            'results' => [
                ['formatted_address' => 'first', 'geometry' => ['location' => ['lat' => 52.16502, 'lng' => 4.48318]]],
                ['formatted_address' => 'second'],
            ],
        ]);
    }

    public function testReturnsTheFirstResult()
    {
        $result = $this->geoCode('Breestraat 1, Leiden', self::okBody());

        $this->assertSame('first', $result['formatted_address']);
        $this->assertSame(52.16502, $result['geometry']['location']['lat']);
        $this->assertSame([], $this->phpErrors);
    }

    public function testRequestUsesTheServerKeyAndTheEncodedAddress()
    {
        $this->geoCode('Breestraat 1, Leiden', self::okBody());

        $this->assertCount(1, HttpsStreamStub::$requests);
        $url = HttpsStreamStub::$requests[0]['url'];
        $this->assertStringStartsWith('https://maps.googleapis.com/maps/api/geocode/json?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        # Never the browser key: that one is published to every visitor
        $this->assertSame('server-key', $query['key']);
        $this->assertSame('Breestraat 1, Leiden', $query['address']);
    }

    /**
     * Regression (#3): without a timeout, a slow or unreachable endpoint held the request for
     * PHP's default_socket_timeout (60 s by default).
     */
    public function testRequestHasATimeout()
    {
        $this->geoCode('Leiden', self::okBody());

        $options = HttpsStreamStub::$requests[0]['options'];
        $this->assertArrayHasKey('timeout', $options['http'] ?? [], 'the request has no http timeout');
        $this->assertGreaterThan(0, $options['http']['timeout']);
        $this->assertLessThan((float) ini_get('default_socket_timeout'), $options['http']['timeout']);
    }

    public function testTimeoutIsConfigurable()
    {
        Config::modify()->set(LatLongField::class, 'geocode_timeout', 2.5);

        $this->geoCode('Leiden', self::okBody());

        $this->assertSame(2.5, HttpsStreamStub::$requests[0]['options']['http']['timeout'] ?? null);
    }

    /**
     * Regression (#3): a failed request made file_get_contents() warn and return false, and then
     * reading 'status' off the null that json_decode() gave warned again.
     */
    public function testNetworkFailureReturnsNullWithoutWarnings()
    {
        $result = $this->geoCode('Leiden', null);

        $this->assertNull($result);
        $this->assertSame([], $this->phpErrors);
    }

    public function testInvalidJsonReturnsNullWithoutWarnings()
    {
        $result = $this->geoCode('Leiden', '<html>Bad gateway</html>');

        $this->assertNull($result);
        $this->assertSame([], $this->phpErrors);
    }

    public function testNonOkStatusReturnsNull()
    {
        $result = $this->geoCode('Leiden', json_encode(['status' => 'REQUEST_DENIED', 'error_message' => 'key invalid', 'results' => []]));

        $this->assertNull($result);
        $this->assertSame([], $this->phpErrors);
    }

    public function testZeroResultsReturnsNull()
    {
        $result = $this->geoCode('nowhere at all', json_encode(['status' => 'ZERO_RESULTS', 'results' => []]));

        $this->assertNull($result);
        $this->assertSame([], $this->phpErrors);
    }

    /**
     * Regression (#3): without GMAPS_API_KEY it returned what user_error() returns (true). It now
     * returns null and makes no request. The E_USER_NOTICE stays (BC: a configuration error that
     * projects see in dev); LatLongFieldTest::testGeoCodeWithoutAKeyDoesNotCallOut pins it too.
     */
    public function testMissingApiKeyReturnsNullWithoutRequest()
    {
        Environment::setEnv('GMAPS_API_KEY', '');

        $result = $this->geoCode('Leiden', self::okBody());

        $this->assertNull($result);
        $this->assertSame([], HttpsStreamStub::$requests, 'no request without a key (and never with the browser key)');
        $this->assertCount(1, $this->phpErrors, 'only the missing-key notice');
        $this->assertStringContainsString('No GMAPS_API_KEY', $this->phpErrors[0]);
    }

    public function testFailuresAreLoggedWithTheServerKeyMasked()
    {
        $this->geoCode('Leiden', null);
        $this->geoCode('Leiden', json_encode(['status' => 'REQUEST_DENIED', 'error_message' => 'key invalid']));
        $this->geoCode('Leiden', 'not json');

        $logged = $this->logged();
        $this->assertCount(3, $logged, 'one warning per failure');
        $this->assertTrue($this->log->hasWarningRecords());
        $this->assertStringContainsString('request failed for "Leiden"', $logged[0]);
        $this->assertStringContainsString('REQUEST_DENIED (key invalid)', $logged[1]);
        $this->assertStringContainsString('not JSON', $logged[2]);
        # The failed-request warning quotes the URL, which carries the server key
        foreach ($logged as $message) {
            $this->assertStringNotContainsString('server-key', $message);
        }
    }

    public function testSuccessAndZeroResultsLogNothing()
    {
        $this->geoCode('Leiden', self::okBody());
        $this->geoCode('nowhere', json_encode(['status' => 'ZERO_RESULTS', 'results' => []]));

        $this->assertSame([], $this->logged());
    }
}
