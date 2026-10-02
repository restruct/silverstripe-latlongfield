<?php

namespace Restruct\LlBrowser;

use SilverStripe\Admin\ModelAdmin;

/**
 * BROWSER-TEST FIXTURE ONLY - the CMS screen the specs open: a place's edit form under
 * /admin/ll-browser/places (see LlBPlace for why this never loads in a real install).
 */
class LlBAdmin extends ModelAdmin
{
    private static $url_segment = 'll-browser';

    private static $menu_title = 'LatLong browser test';

    # Keyed managed_models (SS5 and SS6): the key becomes the URL segment and the list grid's name.
    private static $managed_models = [
        'places' => ['dataClass' => LlBPlace::class, 'title' => 'Places'],
    ];
}
