<?php

namespace Restruct\LlBrowser;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * BROWSER-TEST FIXTURE ONLY - lets a spec start from a known record:
 * GET /admin/ll-reset/reseed?title=...&setup=plain|address&gps=... answers {"id": <record ID>}.
 *
 * A LeftAndMain because the admin routes those by url_segment with no YAML: the fixtures are copied
 * into app/src/, where no _config is read. LeftAndMain's own access check (CMS access for this
 * section) applies, so only the logged-in admin can call it. See LlBPlace for why this never loads
 * in a real install.
 */
class LlBResetAdmin extends LeftAndMain
{
    private static $url_segment = 'll-reset';

    private static $menu_title = 'LatLong browser reset';

    private static $allowed_actions = ['reseed'];

    public function reseed(HTTPRequest $request): HTTPResponse
    {
        $title = (string) $request->getVar('title');
        if ($title === '') {
            return $this->httpError(400, 'title is required');
        }
        $place = LlBPlace::reseed($title, (string) ($request->getVar('setup') ?: 'plain'), (string) $request->getVar('gps'));

        return HTTPResponse::create(json_encode(['id' => $place->ID]))
            ->addHeader('Content-Type', 'application/json');
    }
}
