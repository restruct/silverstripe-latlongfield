<?php

namespace Restruct\LlBrowser;

use Restruct\SilverStripe\Forms\LatLongField;
use SilverStripe\ORM\DataObject;

/**
 * BROWSER-TEST FIXTURE ONLY - a record with a LatLongField, edited in LlBAdmin.
 *
 * Never loaded by a real install: it lives under tests/browser/, which carries a _manifest_exclude
 * marker, and the browser-test runner copies it into a scratch host's app/ before dev/build.
 * Written to load on both Silverstripe 5 and 6 (no class imports that moved between the two).
 *
 * Specs do not rely on seeded rows: each one asks LlBResetAdmin for a fresh record of its own.
 *
 * @property string $Title
 * @property string $GPS "lat,long"
 * @property string $StreetAddress
 * @property string $City
 * @property string $Setup 'plain' (the field on its own) or 'address' (address input fields + map options)
 */
class LlBPlace extends DataObject
{
    # Short table names: no namespaced defaults, MySQL caps table names at 64 characters.
    private static $table_name = 'LlBPlace';

    private static $singular_name = 'Place';

    private static $db = [
        'Title' => 'Varchar(255)',
        'GPS' => 'Varchar(64)',
        'StreetAddress' => 'Varchar(255)',
        'City' => 'Varchar(255)',
        'Setup' => 'Varchar(20)',
    ];

    private static $summary_fields = [
        'Title' => 'Title',
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['GPS', 'Setup']);

        # The README's usage: the field replaces a plain text field for the "lat,long" column.
        $gps = LatLongField::create('GPS', 'Position (lat.,long.)');
        if ($this->Setup === 'address') {
            # Look the address up from the two other fields, and open the map with custom options.
            $gps->setAddressInputFields(['StreetAddress', 'City']);
            $gps->setLocationPickerOptions(['defaultZoom' => 12, 'maptype' => 'SATELLITE']);
        }
        $fields->addFieldToTab('Root.Main', $gps);

        return $fields;
    }

    /** (Re)create the record titled so. Called by LlBResetAdmin. */
    public static function reseed(string $title, string $setup, string $gps): self
    {
        foreach (self::get()->filter('Title', $title) as $old) {
            $old->delete();
        }
        $place = self::create([
            'Title' => $title,
            'Setup' => $setup,
            'GPS' => $gps,
            'StreetAddress' => $setup === 'address' ? 'Breestraat 1' : '',
            'City' => $setup === 'address' ? 'Leiden' : '',
        ]);
        $place->write();

        return $place;
    }
}
