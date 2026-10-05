<?php

namespace App\Enums;

/** Every resource type the mock stores (data-model §2). */
enum ResourceType: string
{
    case Site = 'site';
    case PendingDevice = 'pending_device';
    case Device = 'device';
    case Client = 'client';
    case Network = 'network';
    case WifiBroadcast = 'wifi_broadcast';
    case Voucher = 'voucher';
    case FirewallZone = 'firewall_zone';
    case FirewallPolicy = 'firewall_policy';
    case AclRule = 'acl_rule';
    case DnsPolicy = 'dns_policy';
    case TrafficMatchingList = 'traffic_matching_list';
    case Country = 'country';
    case DpiApplication = 'dpi_application';
    case DpiCategory = 'dpi_category';
    case DeviceTag = 'device_tag';
    case RadiusProfile = 'radius_profile';
    case VpnServer = 'vpn_server';
    case S2sTunnel = 's2s_tunnel';
    case Wan = 'wan';

    /** Global types carry no site. */
    public function isGlobal(): bool
    {
        return in_array($this, [self::Site, self::PendingDevice, self::Country, self::DpiApplication, self::DpiCategory], true);
    }
}
