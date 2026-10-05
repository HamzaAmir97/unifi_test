<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Mock\Errors\MockError;
use App\Mock\Manifest\Manifest;
use Illuminate\Http\Request;

/**
 * TEMPORARY (G1–G3). A request that is in the collection but not built yet answers 501,
 * so it never looks like a wrong URL. Deleted in G3.14 once every request is mocked.
 */
class NotMockedYetController extends Controller
{
    public function __invoke(Request $request, Manifest $manifest)
    {
        throw MockError::notMockedYet($manifest->entry($request->route()->defaults['manifestEntry'])['name']);
    }
}
