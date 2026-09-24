<?php

namespace Restruct\LatLong\Tests;

use Restruct\SilverStripe\Forms\LatLongField;
use SilverStripe\Dev\SapphireTest;

/**
 * Regressions fixed in 1.0.7 on the v1 line (the full suite lives on main / 2.x).
 */
class LatLongFieldHotfixTest extends SapphireTest
{
    /**
     * calCulateDistance() threw a TypeError on PHP 8 for a malformed coordinate; projects pass in a
     * visitor's cookie, so any visitor could trigger a server error.
     */
    public function testCalculateDistanceWithAMalformedCoordinateReturnsNull()
    {
        foreach (['', 'abc', '52.1', 'abc,def', '52abc,4', '52.1,4.4,1', null, []] as $bad) {
            $this->assertNull(LatLongField::calCulateDistance($bad, '52.1,4.4'), var_export($bad, true) . ' as from');
            $this->assertNull(LatLongField::calCulateDistance('52.1,4.4', $bad), var_export($bad, true) . ' as to');
        }
    }

    /**
     * Values that 1.0.6 parsed only partly gave a distance with a PHP warning; they now return
     * null (see the changelog). 1.0.6 gave: trailing text 340.64, a third part 318.21, decimal
     * commas 95.29, a float 67.21.
     */
    public function testCalculateDistanceWithAPartlyParsableCoordinateReturnsNull()
    {
        # trailing text, a third part, decimal commas, and a float instead of a "lat,long" string
        foreach (['48.858278,2.294254abc', '52.1,4.4,9', '52,1;4,4', 52.1] as $partial) {
            $this->assertNull(
                LatLongField::calCulateDistance($partial, '51.500705,-0.124575', 2),
                var_export($partial, true) . ' as from'
            );
            $this->assertNull(
                LatLongField::calCulateDistance('51.500705,-0.124575', $partial, 2),
                var_export($partial, true) . ' as to'
            );
        }
    }

    /**
     * Valid coordinates still give the same distance as 1.0.6 (Eiffel Tower to Big Ben).
     */
    public function testCalculateDistanceIsUnchangedForValidCoordinates()
    {
        $this->assertSame(340.6, LatLongField::calCulateDistance('48.858278,2.294254', '51.500705,-0.124575', 1));
        $this->assertEquals(341, LatLongField::calCulateDistance('48.858278,2.294254', '51.500705,-0.124575'));
        $this->assertSame(340.6, LatLongField::calCulateDistance('48.858278, 2.294254', '51.500705, -0.124575', 1));
    }

    /**
     * getAddressInputFields() counted an undefined variable, so a field with no address fields
     * threw a TypeError on PHP 8 as soon as it rendered.
     */
    public function testFieldWithoutAddressInputFieldsRenders()
    {
        $field = LatLongField::create('GPS', 'Position');
        $this->assertNull($field->getAddressInputFields());
        $this->assertStringContainsString('name="GPS"', (string) $field->Field());
    }
}
