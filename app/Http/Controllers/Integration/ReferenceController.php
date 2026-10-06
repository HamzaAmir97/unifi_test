<?php

namespace App\Http\Controllers\Integration;

use App\Enums\ResourceType;
use App\Mock\Presenters\PayloadPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Supporting Resources: read-only reference lists. Countries and DPI are global; the rest
 * are site-scoped. "List Device Tags" has no `filter` parameter in the collection but
 * declares filterable properties, so a filter sent anyway is honoured.
 */
class ReferenceController extends IntegrationController
{
    public function countries(Request $request): JsonResponse
    {
        return $this->global($request, ResourceType::Country, 'list-countries');
    }

    public function dpiApplications(Request $request): JsonResponse
    {
        return $this->global($request, ResourceType::DpiApplication, 'list-dpi-applications');
    }

    public function dpiCategories(Request $request): JsonResponse
    {
        return $this->global($request, ResourceType::DpiCategory, 'list-dpi-application-categories');
    }

    public function deviceTags(Request $request, string $siteId): JsonResponse
    {
        return $this->scoped($request, $siteId, ResourceType::DeviceTag, 'list-device-tags');
    }

    public function radiusProfiles(Request $request, string $siteId): JsonResponse
    {
        return $this->scoped($request, $siteId, ResourceType::RadiusProfile, 'list-radius-profiles');
    }

    public function vpnServers(Request $request, string $siteId): JsonResponse
    {
        return $this->scoped($request, $siteId, ResourceType::VpnServer, 'list-vpn-servers');
    }

    public function siteToSiteTunnels(Request $request, string $siteId): JsonResponse
    {
        return $this->scoped($request, $siteId, ResourceType::S2sTunnel, 'list-site-to-site-vpn-tunnels');
    }

    public function wans(Request $request, string $siteId): JsonResponse
    {
        return $this->scoped($request, $siteId, ResourceType::Wan, 'list-wan-interfaces');
    }

    /** Countries and DPI entries carry their own id (a code or an integer), not a uuid. */
    private function global(Request $request, ResourceType $type, string $entry): JsonResponse
    {
        return $this->listResponse($request, $entry, $this->store->all($type), PayloadPresenter::for($entry, 'data[]', withId: false));
    }

    private function scoped(Request $request, string $siteId, ResourceType $type, string $entry): JsonResponse
    {
        $this->site($siteId);

        return $this->listResponse($request, $entry, $this->store->all($type, $siteId), PayloadPresenter::for($entry, 'data[]'));
    }
}
