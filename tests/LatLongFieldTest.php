<?php

namespace Restruct\LatLong\Tests;

use Restruct\SilverStripe\Forms\LatLongField;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\TextField;
use SilverStripe\View\Requirements;
use SilverStripe\View\SSViewer;
use SilverStripe\View\ViewLayerData;

/**
 * The field's markup is the contract with client/js: latlongfield.js finds `input.latlong` and
 * reads `data-locationpickeroptions`, jquery.locationpicker.js reads `data-addressfields` and the
 * search/clear buttons around the input. Everything here is checked on the rendered HTML, not on
 * the getters alone, because the defects this suite was written for (a TypeError without address
 * fields; empty data attributes on Silverstripe 6) only show up when the template runs.
 */
class LatLongFieldTest extends SapphireTest
{
    /** @var array environment as it was before each test, restored in tearDown() */
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->envBackup = Environment::getVariables();
        Requirements::clear();
    }

    protected function tearDown(): void
    {
        # Environment is process-wide and SapphireTest does not restore it; a key set here would
        # leak into every later test class in the run.
        Environment::setVariables($this->envBackup);
        Requirements::clear();
        parent::tearDown();
    }

    /**
     * Return the attributes of the rendered <input class="latlong ..."> (the class must match as a
     * whole word: the search button's "btn-latlong-search" must not). as name => decoded value.
     */
    private function inputAttributes(string $html): array
    {
        $this->assertSame(1, preg_match('#<input\s+([^>]*\bclass="(?:[^"]*\s)?latlong(?:\s[^"]*)?"[^>]*)/?>#', $html, $m), 'no input.latlong in: ' . $html);
        preg_match_all('#([\w-]+)=(["\'])(.*?)\2#s', $m[1], $pairs, PREG_SET_ORDER);
        $attrs = [];
        foreach ($pairs as $pair) {
            $attrs[$pair[1]] = html_entity_decode($pair[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return $attrs;
    }

    # --- Rendering: regressions -------------------------------------------------------------

    /**
     * Regression: getAddressInputFields() counted an undefined variable, so on PHP 8 a field with
     * no address input fields threw a TypeError as soon as its template ran.
     */
    public function testFieldWithoutAddressInputFieldsRenders()
    {
        $field = LatLongField::create('GPS', 'Position');
        $html = (string) $field->Field();

        $attrs = $this->inputAttributes($html);
        $this->assertSame('GPS', $attrs['name']);
        $this->assertArrayNotHasKey('readonly', $attrs, 'without address fields the editor types into the field itself');
        $this->assertNull(json_decode($attrs['data-addressfields'], true));
        $this->assertNull(json_decode($attrs['data-locationpickeroptions'], true));
    }

    /**
     * Regression: the template piped the getters' arrays through `.JSON`. Silverstripe 6 wraps a
     * list array in an ArrayList, which has no JSON(), so both data attributes rendered empty and
     * the address lookup and picker options were silently lost.
     */
    public function testAddressInputFieldsReachTheDataAttribute()
    {
        $field = LatLongField::create('GPS', 'Position');
        $field->setAddressInputFields(['StreetAddress', 'City']);

        $attrs = $this->inputAttributes((string) $field->Field());
        $this->assertSame(['StreetAddress', 'City'], json_decode($attrs['data-addressfields'], true));
    }

    public function testLocationPickerOptionsReachTheDataAttribute()
    {
        $field = LatLongField::create('GPS', 'Position');
        $field->setLocationPickerOptions(['defaultZoom' => 10, 'defaultLat' => 52.1]);

        $attrs = $this->inputAttributes((string) $field->Field());
        $this->assertSame(['defaultZoom' => 10, 'defaultLat' => 52.1], json_decode($attrs['data-locationpickeroptions'], true));
    }

    /**
     * The data attributes are single-quoted in the template, so a quote in an option value must be
     * escaped or it ends the attribute early.
     */
    public function testDataAttributesAreEscaped()
    {
        $field = LatLongField::create('GPS', 'Position');
        $field->setLocationPickerOptions(['maptype' => "it's \"quoted\" <b>"]);

        $html = (string) $field->Field();
        $this->assertStringNotContainsString("it's", $html);
        $this->assertStringNotContainsString('<b>', $html);
        $attrs = $this->inputAttributes($html);
        $this->assertSame(['maptype' => "it's \"quoted\" <b>"], json_decode($attrs['data-locationpickeroptions'], true));
    }

    /**
     * Regression: Field() called `parent::Field($properties = [])`, which threw away whatever the
     * caller passed in.
     */
    public function testFieldPassesPropertiesToTheTemplate()
    {
        $field = LatLongField::create('GPS', 'Position');
        $field->setAddressInputFields(['StreetAddress']);

        $attrs = $this->inputAttributes((string) $field->Field(['AddressInputFieldsJSON' => '["Overridden"]']));
        $this->assertSame(['Overridden'], json_decode($attrs['data-addressfields'], true));
    }

    # --- Rendering: markup the scripts depend on --------------------------------------------

    public function testMarkupCarriesWhatTheScriptsLookFor()
    {
        $field = LatLongField::create('GPS', 'Position', '52.1,4.4');
        $html = (string) $field->Field();

        $this->assertStringContainsString('class="input-group latlong-fieldgroup"', $html);
        $this->assertMatchesRegularExpression('#<input class="[^"]*\bbtn-latlong-search\b#', $html);
        $this->assertMatchesRegularExpression('#<input class="[^"]*\bbtn-latlong-clear\b#', $html);

        $attrs = $this->inputAttributes($html);
        $this->assertSame('52.1,4.4', $attrs['value']);
        $this->assertStringContainsString('latlong', $attrs['class']);
        $this->assertStringContainsString('text', $attrs['class'], 'the "text" class is added for CMS styling');
    }

    /**
     * Regression: the template shipped Bootstrap 4 input-group markup onto the Silverstripe 6 CMS,
     * whose Bootstrap 5 stylesheet has no rules for .input-group-prepend / .input-group-append /
     * .font-weight-bold. The expected markup follows the Bootstrap major the installed admin
     * declares in its package.json, not the field's own switch, so a wrong switch fails here.
     */
    public function testInputGroupMarkupMatchesTheCmsBootstrapVersion()
    {
        $bootstrap = $this->adminBootstrapMajor();
        $field = LatLongField::create('GPS', 'Position', '52.1,4.4');
        $html = (string) $field->Field();

        if ($bootstrap >= 5) {
            $this->assertStringNotContainsString('input-group-prepend', $html);
            $this->assertStringNotContainsString('input-group-append', $html);
            $this->assertStringNotContainsString('font-weight-bold', $html);
            # Buttons and input are direct children of .input-group, which BS5 styles
            $this->assertMatchesRegularExpression('#<div class="input-group latlong-fieldgroup">\s*<input class="[^"]*\bbtn-latlong-search\b#', $html);
            $this->assertMatchesRegularExpression('#<input class="[^"]*\bbtn-latlong-clear fw-bold"#', $html);
        } else {
            $this->assertMatchesRegularExpression('#<div class="input-group-prepend">\s*<input class="[^"]*\bbtn-latlong-search\b#', $html);
            $this->assertMatchesRegularExpression('#<div class="input-group-append">\s*<input class="[^"]*\bbtn-latlong-clear font-weight-bold"#', $html);
            $this->assertStringNotContainsString('fw-bold', $html);
        }
        # latlongfield.js binds the clear button through input.parent(): it must stay a descendant
        # of the input's parent in both shapes
        $this->assertMatchesRegularExpression('#<div class="input-group latlong-fieldgroup">.*<input type="text"[^>]*class="latlong text".*btn-latlong-clear.*</div>\s*$#s', $html);
    }

    /**
     * Bootstrap major of the installed CMS, from silverstripe/admin's package.json; without admin
     * (or its package.json) the framework major decides: admin 2 (BS4) goes with framework 5,
     * admin 3 (BS5) with framework 6.
     */
    private function adminBootstrapMajor(): int
    {
        $admin = \SilverStripe\Core\Manifest\ModuleLoader::getModule('silverstripe/admin');
        $package = $admin ? $admin->getPath() . '/package.json' : null;
        if ($package && is_readable($package)) {
            $json = json_decode(file_get_contents($package), true);
            $constraint = $json['dependencies']['bootstrap'] ?? $json['devDependencies']['bootstrap'] ?? null;
            if ($constraint && preg_match('#(\d+)#', $constraint, $m)) {
                return (int) $m[1];
            }
        }
        return class_exists(ViewLayerData::class) ? 5 : 4;
    }

    public function testFieldIsReadonlyWhenAddressFieldsAreSet()
    {
        $field = LatLongField::create('GPS', 'Position');
        $field->setAddressInputFields(['StreetAddress']);

        $attrs = $this->inputAttributes((string) $field->Field());
        $this->assertSame('readonly', $attrs['readonly'] ?? null);
    }

    public function testDefaultPlaceholderAndDescription()
    {
        $field = LatLongField::create('GPS', 'Position');
        $attrs = $this->inputAttributes((string) $field->Field());

        $this->assertSame('(empty / no location yet)', $attrs['placeholder']);
        $this->assertStringContainsString('click', (string) $field->getDescription());
    }

    public function testOwnPlaceholderAndDescriptionAreKept()
    {
        $field = LatLongField::create('GPS', 'Position');
        $field->setAttribute('placeholder', 'Mine');
        $field->setDescription('My description');

        $attrs = $this->inputAttributes((string) $field->Field());
        $this->assertSame('Mine', $attrs['placeholder']);
        $this->assertSame('My description', $field->getDescription());
    }

    public function testRightTitleSuppressesTheDefaultDescription()
    {
        $field = LatLongField::create('GPS', 'Position');
        $field->setRightTitle('Right');
        $field->Field();

        $this->assertEmpty($field->getDescription());
    }

    # --- Getters and setters ----------------------------------------------------------------

    public function testGettersReturnNullWhenNothingIsSet()
    {
        $field = LatLongField::create('GPS');
        $this->assertNull($field->getAddressInputFields());
        $this->assertNull($field->getLocationPickerOptions());
    }

    public function testAddressInputFieldsAcceptNamesAndFormFields()
    {
        $field = LatLongField::create('GPS');
        $field->addAddressInputField('StreetAddress');
        $field->addAddressInputField(TextField::create('City'));

        $this->assertSame(['StreetAddress', 'City'], $field->getAddressInputFields());
    }

    public function testSetAddressInputFieldsReplaces()
    {
        $field = LatLongField::create('GPS');
        $field->addAddressInputField('Old');
        $field->setAddressInputFields(['New']);

        $this->assertSame(['New'], $field->getAddressInputFields());
    }

    public function testSetLocationPickerOptionsMerges()
    {
        $field = LatLongField::create('GPS');
        $field->setLocationPickerOptions(['defaultZoom' => 10, 'maptype' => 'ROADMAP']);
        $field->setLocationPickerOptions(['defaultZoom' => 12]);

        $this->assertSame(['defaultZoom' => 12, 'maptype' => 'ROADMAP'], $field->getLocationPickerOptions());
    }

    # --- Requirements and API key -----------------------------------------------------------

    public function testConstructorAddsTheScriptsAndStylesheet()
    {
        Environment::setEnv('GMAPS_API_KEY', 'server-key');
        Environment::setEnv('GMAPS_BROWSER_KEY', '');
        LatLongField::create('GPS');

        $js = array_keys(Requirements::backend()->getJavascript());
        $css = array_keys(Requirements::backend()->getCSS());

        $this->assertContains('//maps.google.com/maps/api/js?key=server-key', $js);
        $this->assertCount(1, preg_grep('#client/js/jquery\.locationpicker\.js$#', $js));
        $this->assertCount(1, preg_grep('#client/js/latlongfield\.js$#', $js));
        $this->assertCount(1, preg_grep('#client/css/latlongfield\.css$#', $css));
    }

    public function testBrowserKeyIsPreferredInTheBrowser()
    {
        Environment::setEnv('GMAPS_API_KEY', 'server-key');
        Environment::setEnv('GMAPS_BROWSER_KEY', 'browser-key');
        LatLongField::create('GPS');

        $this->assertSame('browser-key', LatLongField::gmaps_api_key());
        $this->assertSame('server-key', LatLongField::gmaps_api_key(true));
        $this->assertContains('//maps.google.com/maps/api/js?key=browser-key', array_keys(Requirements::backend()->getJavascript()));
    }

    public function testTemplateGlobalGivesTheBrowserKey()
    {
        Environment::setEnv('GMAPS_API_KEY', 'server-key');
        Environment::setEnv('GMAPS_BROWSER_KEY', 'browser-key');

        $this->assertSame(['GMapsApiKey' => 'gmaps_api_key'], LatLongField::get_template_global_variables());
        $this->assertSame('key=browser-key', trim($this->renderString('key=$GMapsApiKey')));
    }

    /**
     * Render a template string with no model, on either major: Silverstripe 6 moved string
     * rendering to the template engine; Silverstripe 5 has SSViewer::fromString().
     */
    private function renderString(string $template): string
    {
        $engine = 'SilverStripe\\TemplateEngine\\SSTemplateEngine';
        if (class_exists($engine) && class_exists(ViewLayerData::class)) {
            $model = new ViewLayerData(\SilverStripe\Model\ArrayData::create([]));
            return $engine::create()->renderString($template, $model, [], false);
        }
        return (string) SSViewer::fromString($template)->process(\SilverStripe\View\ArrayData::create([]));
    }

    # --- Static helpers ---------------------------------------------------------------------

    /**
     * Regression: validateLatLong() tested the parts with floatval(), so a coordinate on the
     * equator or the prime meridian ("0,5.1") was rejected, while "52abc,4" and out-of-range
     * values passed.
     */
    public function testValidateLatLong()
    {
        foreach (['52.12759,5.429787', '0,5.1', '51.5,0', '-33.92, 18.42', '90,-180', '-90,180'] as $valid) {
            $this->assertTrue(LatLongField::validateLatLong($valid), "'$valid' should be valid");
        }
        foreach (['', '52.1', 'abc,def', '52abc,4', '52.1,4.4,1', '91,0', '0,181', '-90.5,0', null] as $invalid) {
            $this->assertFalse(LatLongField::validateLatLong($invalid), var_export($invalid, true) . ' should be invalid');
        }
    }

    public function testCalculateDistance()
    {
        # Eiffel Tower to Big Ben: about 340 km
        $km = LatLongField::calCulateDistance('48.858278,2.294254', '51.500705,-0.124575', 1);
        $this->assertEqualsWithDelta(340.5, $km, 1.0);
        # $decimals is honoured (haversine on the 6372.797 km mean radius gives 340.6375...)
        $this->assertSame(340.6, $km);
        $this->assertSame(340.64, LatLongField::calCulateDistance('48.858278,2.294254', '51.500705,-0.124575', 2));

        $this->assertEquals(341,LatLongField::calCulateDistance('48.858278,2.294254', '51.500705,-0.124575'));
        $this->assertEquals(0, LatLongField::calCulateDistance('52.1,4.4', '52.1,4.4'));
        # A space after the comma, as in hand-typed coordinates
        $this->assertEqualsWithDelta(340.5, LatLongField::calCulateDistance('48.858278, 2.294254', '51.500705, -0.124575', 1), 1.0);
    }

    /**
     * Regression: a malformed coordinate (projects pass one from a visitor's cookie) threw a
     * TypeError ("Unsupported operand types: string * float") on PHP 8, a 500 on the front end.
     */
    public function testCalculateDistanceWithAnInvalidCoordinateReturnsNull()
    {
        $this->assertNull(LatLongField::calCulateDistance('52.1,4.4', 'garbage'));
        $this->assertNull(LatLongField::calCulateDistance('garbage', '52.1,4.4'));
        $this->assertNull(LatLongField::calCulateDistance('52.1,4.4', ''));
    }

    public function testGeoCodeWithoutAKeyDoesNotCallOut()
    {
        Environment::setEnv('GMAPS_API_KEY', '');
        Environment::setEnv('GMAPS_BROWSER_KEY', 'browser-key');

        # user_error() is an E_USER_NOTICE; the runner turns it into an exception or a notice
        # depending on the major, so assert on the notice itself.
        $raised = null;
        set_error_handler(function ($no, $str) use (&$raised) {
            $raised = $str;
            return true;
        }, E_USER_NOTICE);
        try {
            LatLongField::GeoCode('Leiden');
        } finally {
            restore_error_handler();
        }
        $this->assertStringContainsString('No GMAPS_API_KEY', (string) $raised, 'GeoCode must use the primary key, never the browser key');
    }
}
