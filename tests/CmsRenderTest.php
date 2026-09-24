<?php

namespace Restruct\LatLong\Tests;

use Restruct\LatLong\Tests\Stub\LatLongTestPage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\FunctionalTest;

/**
 * Renders a real CMS page edit form containing a LatLongField, as a logged-in admin. Whether the
 * map then opens is browser behaviour; what this pins is everything the scripts depend on: the
 * Google Maps API and the module's assets are on the page, and the input carries its class, value
 * and data attributes inside the edit form.
 */
class CmsRenderTest extends FunctionalTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        LatLongTestPage::class,
    ];

    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(SiteTree::class)) {
            $this->markTestSkipped('silverstripe/cms is not installed');
        }
        $this->envBackup = Environment::getVariables();
        Environment::setEnv('GMAPS_API_KEY', 'cms-test-key');
        Environment::setEnv('GMAPS_BROWSER_KEY', '');
    }

    protected function tearDown(): void
    {
        Environment::setVariables($this->envBackup);
        parent::tearDown();
    }

    public function testCmsEditFormRendersTheFieldAndLoadsItsAssets()
    {
        $page = LatLongTestPage::create(['Title' => 'Latlong test', 'GPS' => '52.16502,4.48318']);
        $page->write();

        $this->logInWithPermission('ADMIN');
        $response = $this->get($page->CMSEditLink());

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();

        # Assets, added by the field's constructor
        $this->assertMatchesRegularExpression('#<script[^>]+src="//maps\.google\.com/maps/api/js\?key=cms-test-key#', $body);
        $this->assertMatchesRegularExpression('#<script[^>]+src="[^"]*/client/js/jquery\.locationpicker\.js#', $body);
        $this->assertMatchesRegularExpression('#<script[^>]+src="[^"]*/client/js/latlongfield\.js#', $body);
        $this->assertMatchesRegularExpression('#<link[^>]+href="[^"]*/client/css/latlongfield\.css#', $body);

        # The field, inside the edit form, with its stored value and the data the scripts read
        $this->assertMatchesRegularExpression(
            '#<input type="text" name="GPS" value="52\.16502,4\.48318" class="latlong text" id="Form_EditForm_GPS" readonly="readonly"#',
            $body
        );
        $this->assertStringContainsString("data-addressfields='[&quot;StreetAddress&quot;,&quot;City&quot;]'", $body);
        $this->assertStringContainsString("data-locationpickeroptions='{&quot;defaultZoom&quot;:12}'", $body);
        $this->assertMatchesRegularExpression('#btn-latlong-search#', $body);
    }
}
