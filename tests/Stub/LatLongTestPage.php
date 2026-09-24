<?php

namespace Restruct\LatLong\Tests\Stub;

use Restruct\SilverStripe\Forms\LatLongField;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\TextField;

# silverstripe/cms is not a requirement of this module (it only needs framework), so this stub must
# not be declared without it: in test mode the tests/ dir is in the class manifest, and a class
# extending a missing parent fatals the manifest rebuild in every consuming project. The guard
# sits AFTER the use imports so SiteTree::class resolves to the real FQCN.
if (!class_exists(SiteTree::class)) {
    return;
}

/**
 * Page type used by CmsRenderTest to render a LatLongField, wired to two address fields, inside a
 * real CMS edit form.
 */
class LatLongTestPage extends SiteTree implements TestOnly
{
    # Short table name: a stub under the full test namespace makes long table names.
    private static $table_name = 'LatLongTestPage';

    private static $db = [
        'GPS' => 'Varchar(64)',
        'StreetAddress' => 'Varchar',
        'City' => 'Varchar',
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->addFieldToTab('Root.Main', TextField::create('StreetAddress'));
        $fields->addFieldToTab('Root.Main', TextField::create('City'));
        $fields->addFieldToTab('Root.Main', $gps = LatLongField::create('GPS', 'Position'));
        $gps->setAddressInputFields(['StreetAddress', 'City']);
        $gps->setLocationPickerOptions(['defaultZoom' => 12]);

        return $fields;
    }
}
