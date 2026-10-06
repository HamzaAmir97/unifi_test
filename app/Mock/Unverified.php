<?php

namespace App\Mock;

/**
 * The register of every behaviour chosen because the collection does not state it
 * (spec FR-025, SC-006). One constant per assumption; the docblock of each names its gap.
 * `php artisan mock:unverified` lists them all by reflection.
 *
 * Gaps (plan §gaps): G1 error envelope · G2 filter syntax · G3 limits · G4 enum values of
 * `type` fields · G5 action names · G6 default and maximum `limit` · G7 voucher code format ·
 * G8 does an unused voucher expire · G9 rate limits · G10 console-mismatch response ·
 * G11 required/optional inferred from field order, absent vs null · G12 client `access` shape.
 *
 * When D4 (official OpenAPI file or a read-only probe) verifies or contradicts one, the
 * constant is corrected or removed here — in one place.
 */
final class Unverified
{
    /** G1 — the fields of every error response, in this order. */
    public const ERROR_ENVELOPE_FIELDS = ['statusCode', 'statusName', 'code', 'message', 'timestamp', 'requestPath', 'requestId'];

    /** G1 — error codes used in the envelope's `code` field. */
    public const ERROR_CODES = [
        'request_invalid' => 'api.request.invalid',
        'filter_invalid' => 'api.request.invalid-filter',
        'unauthorized' => 'api.authentication.failed',
        'not_found' => 'api.resource.not-found',
        'method_not_allowed' => 'api.request.method-not-allowed',
        'conflict' => 'api.resource.conflict',
        'internal' => 'api.internal-error',
        'fault' => 'api.mock.injected-fault',
        'timeout' => 'api.mock.injected-timeout',
    ];

    /** G2 — filter grammar: `property.function(args)` with `and(...)`, `or(...)`, `not(...)`; strings in single quotes, `\'` escapes a quote. */
    public const FILTER_GRAMMAR = "prop.fn(args) | and(e, e, ...) | or(e, e, ...) | not(e); strings '...' with \\' escape";

    /** G2 — the wildcard character of `like`. */
    public const FILTER_LIKE_WILDCARD = '*';

    /** G6 — default `limit` for every list except vouchers (the collection sends 25). */
    public const DEFAULT_LIMIT = 25;

    /** G6 — default `limit` of the voucher list (the request sends 100; its example response says 25). */
    public const VOUCHER_DEFAULT_LIMIT = 100;

    /** G6 — maximum `limit`; above it the request is refused with 400. */
    public const MAX_LIMIT = 200;

    /** G3 — maximum vouchers created by one generate request. */
    public const MAX_VOUCHERS_PER_GENERATE = 1000;

    /** G7 — a voucher code is this many decimal digits. */
    public const VOUCHER_CODE_LENGTH = 10;

    /** G8 — an unused voucher never expires on the clock alone; expiry runs from activation. */
    public const UNUSED_VOUCHER_EXPIRES = false;

    /** G10 — status answered when the `consoleId` in the path is not the mock's. */
    public const CONSOLE_MISMATCH_STATUS = 404;

    /** G1 — refusal status: deleting a network still referenced, without `force=true`. */
    public const NETWORK_IN_USE_STATUS = 409;

    /** G1 — refusal status: deleting the default network. */
    public const DEFAULT_NETWORK_DELETE_STATUS = 409;

    /** G1 — refusal status: editing or deleting a system-defined firewall zone. */
    public const SYSTEM_ZONE_EDIT_STATUS = 400;

    /** G1 — refusal status: an ordering PUT whose ids are not exactly the current set. */
    public const ORDERING_MISMATCH_STATUS = 400;

    /** G1 — refusal status: a duplicate `vlanId` within a site. */
    public const DUPLICATE_VLAN_STATUS = 400;

    /** G1 — refusal status: redeeming an expired voucher (control plane). */
    public const REDEEM_EXPIRED_STATUS = 409;

    /** G1 — refusal status: redeeming a voucher whose guest limit is reached (control plane). */
    public const REDEEM_FULL_STATUS = 409;

    /** G1 — refusal status: adding a pending device whose MAC already exists (control plane). */
    public const DUPLICATE_MAC_STATUS = 409;

    /** G9 — no rate limit is enforced; throttling exists only as an injected fault. */
    public const RATE_LIMITED = false;

    /** G12 — the client `access` object: only `access.type` and `access.authorized` are known (from the filter table). */
    public const CLIENT_ACCESS_SHAPE = ['type', 'authorized'];

    /** G12 — `access` of the client a voucher redemption creates. */
    public const REDEEMED_CLIENT_ACCESS = ['type' => 'GUEST', 'authorized' => true];

    /** G11 — required fields come before optional ones, each alphabetical; the boundary is the first alphabetical descent. */
    public const FIELD_ORDER_RULE = 'required ascending, then optional ascending; split at the first descent; no descent = all required';

    /** G11 — what an optional field with no value looks like by default: "omit" or "null". */
    public const OPTIONAL_FIELD_DEFAULT = 'omit';

    /** G5 — client action names the mock accepts (the collection documents the body `{action}` only). */
    public const CLIENT_ACTIONS = ['AUTHORIZE_GUEST_ACCESS', 'UNAUTHORIZE_GUEST_ACCESS'];

    /** G5 — device action names the mock accepts. */
    public const DEVICE_ACTIONS = ['RESTART'];

    /** G5 — port action names the mock accepts. */
    public const PORT_ACTIONS = ['POWER_CYCLE'];

    /** G4 — values the mock invents for `type`-like fields, beyond those seen in the collection's examples. */
    public const INVENTED_ENUM_VALUES = [
        'type' => ['GUEST', 'WIRED', 'WIRELESS', 'VPN', 'STANDARD', 'IOT_OPTIMIZED', 'WPA2_PERSONAL', 'WPA3_PERSONAL', 'OPEN',
            'NETWORK', 'ALL_DEVICES', 'DEVICES', 'HOTSPOT', 'ALLOW', 'BLOCK', 'REJECT', 'ANY', 'NETWORKS', 'IP_ADDRESSES',
            'IPV4', 'MAC', 'A_RECORD', 'AAAA_RECORD', 'CNAME_RECORD', 'MX_RECORD', 'TXT_RECORD', 'SRV_RECORD', 'FORWARD_DOMAIN',
            'PORTS', 'IPV4_ADDRESSES', 'IPV6_ADDRESSES', 'DOMAINS', 'SPECIFIC', 'NATIVE', 'STATIC', 'LACP',
            'L2TP', 'WIREGUARD', 'OPENVPN', 'IPSEC', 'IP_ADDRESS', 'PORT', 'NETWORK_ID'],
        'mode' => ['ALWAYS', 'AUTO', 'OFF'],
        'ipVersion' => ['IPV4', 'IPV6', 'IPV4_AND_IPV6'],
        'action' => ['RESTART', 'POWER_CYCLE', 'AUTHORIZE_GUEST_ACCESS', 'UNAUTHORIZE_GUEST_ACCESS', 'ALLOW', 'BLOCK'],
        'state' => ['ONLINE', 'OFFLINE'],
    ];

    /** G4 — the request fields whose values are checked against the known values (warning, or 400 in strict mode). */
    public const ENUM_CHECKED_FIELDS = ['type', 'action', 'mode', 'ipVersion', 'ipsecFilter', 'connectionStateFilter', 'state'];

    /** G5 — a device stays UPDATING this many seconds after a RESTART action. */
    public const DEVICE_RESTART_SECONDS = 60;

    /** G1 — refusal status: deleting a firewall zone still used by a policy. */
    public const ZONE_IN_USE_STATUS = 409;

    /** G4 — the state an adopted device takes (the collection lists only transitional states). */
    public const ADOPTED_DEVICE_STATE = 'ONLINE';

    /** G4 — which DNS policy fields apply to each `type` (the collection declares 17 filterable fields, no mapping). */
    public const DNS_FIELDS_BY_TYPE = [
        'A_RECORD' => ['domain', 'ipv4Address', 'ttlSeconds'],
        'AAAA_RECORD' => ['domain', 'ipv6Address', 'ttlSeconds'],
        'CNAME_RECORD' => ['domain', 'targetDomain', 'ttlSeconds'],
        'MX_RECORD' => ['domain', 'mailServerDomain', 'priority'],
        'TXT_RECORD' => ['domain', 'text'],
        'SRV_RECORD' => ['domain', 'serverDomain', 'service', 'protocol', 'port', 'priority', 'weight'],
        'FORWARD_DOMAIN' => ['domain', 'ipAddress'],
    ];

    /** G4 — the reference type a network reference names for broadcasts and zones (only CLIENT and OSPF_ROUTE are seen). */
    public const NETWORK_REFERENCE_TYPES = ['WIFI_BROADCAST', 'FIREWALL_ZONE', 'CLIENT'];

    /** G1 — a timed-out fault answers this status after sleeping. */
    public const TIMEOUT_STATUS = 504;
}
