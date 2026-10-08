<?php

namespace Restruct\LatLong\Tests;

use Restruct\SilverStripe\Forms\LatLongField;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\View\Requirements;

/**
 * Regression for issue #4: the constructor added the stylesheet, both scripts and the Google Maps
 * API (with the key) to Requirements, so building a form or getCMSFields() without rendering it
 * (exports, API responses, tests) still queued them for whatever page the request rendered.
 * They are added when the field renders now.
 */
class FieldRequirementsTest extends SapphireTest
{
    /** @var array environment as it was before each test, restored in tearDown() */
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->envBackup = Environment::getVariables();
        Environment::setEnv('GMAPS_API_KEY', 'req-test-key');
        Environment::setEnv('GMAPS_BROWSER_KEY', '');
        Requirements::clear();
    }

    protected function tearDown(): void
    {
        Environment::setVariables($this->envBackup);
        Requirements::clear();
        parent::tearDown();
    }

    private static function queued(): array
    {
        $backend = Requirements::backend();
        return array_merge(array_keys($backend->getJavascript()), array_keys($backend->getCSS()));
    }

    private static function assertQueued(string $needle, array $queued): void
    {
        $hits = array_filter($queued, fn ($path) => str_contains($path, $needle));
        static::assertNotEmpty($hits, "$needle is not queued; queued: " . implode(', ', $queued));
    }

    public function testConstructingTheFieldQueuesNothing()
    {
        LatLongField::create('GPS', 'Position');

        $this->assertSame([], self::queued());
    }

    public function testRenderingTheFieldQueuesItsAssets()
    {
        LatLongField::create('GPS', 'Position')->Field();

        $queued = self::queued();
        self::assertQueued('//maps.google.com/maps/api/js?key=req-test-key', $queued);
        self::assertQueued('client/js/jquery.locationpicker.js', $queued);
        self::assertQueued('client/js/latlongfield.js', $queued);
        self::assertQueued('client/css/latlongfield.css', $queued);
    }

    public function testRenderingTheHolderQueuesItsAssets()
    {
        # The usual path in a form: the holder template renders $Field
        LatLongField::create('GPS', 'Position')->FieldHolder();

        self::assertQueued('client/js/latlongfield.js', self::queued());
    }
}
