<?php

namespace Restruct\LatLong\Tests;

use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\Dev\SapphireTest;

/**
 * The field loads its script and stylesheet as module resources. Each can fail silently: a
 * resource that does not exist, or one outside a composer `extra.expose` directory, gives the CMS a
 * /_resources URL that 404s with nothing wrong in PHP.
 */
class ResourcesTest extends SapphireTest
{
    private const MODULE = 'restruct/silverstripe-latlongfield';

    /** The resources LatLongField::__construct() requires, module-relative */
    private const RESOURCES = [
        'client/css/latlongfield.css',
        'client/js/jquery.locationpicker.js',
        'client/js/latlongfield.js',
    ];

    public function testConstructorRequiresExactlyTheseResources()
    {
        # Read the requirement calls out of the source, so a renamed or added resource that is not
        # listed above fails here instead of going unchecked.
        $module = ModuleLoader::getModule(self::MODULE);
        $src = file_get_contents($module->getPath() . '/src/LatLongField.php');
        preg_match_all("#'" . preg_quote(self::MODULE, '#') . ":([^']+)'#", $src, $m);

        $this->assertEqualsCanonicalizing(self::RESOURCES, $m[1]);
    }

    public function testEveryResourceExistsAndIsExposed()
    {
        $module = ModuleLoader::getModule(self::MODULE);
        $composer = json_decode(file_get_contents($module->getPath() . '/composer.json'), true);
        $exposed = $composer['extra']['expose'] ?? [];

        foreach (self::RESOURCES as $relative) {
            $path = ModuleResourceLoader::singleton()->resolvePath(self::MODULE . ':' . $relative);
            $this->assertFileExists(BASE_PATH . '/' . $path, "$relative does not resolve to a file");

            $inside = array_filter($exposed, fn ($dir) => str_starts_with($relative, rtrim($dir, '/') . '/'));
            $this->assertNotEmpty($inside, "$relative is not inside any extra.expose directory of composer.json");
        }
    }
}
