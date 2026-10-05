<?php

namespace App\Mock\OpenApi;

/**
 * OpenAPI operations of the /__mock control plane (contracts/control-api.md).
 * Empty until G4 builds the control plane; G2's redeem and clock are listed as they land.
 */
final class ControlPlaneSpec
{
    public const TAG = 'Mock control plane';

    /** @return array<string, array<string, mixed>> path (after /__mock) => operations */
    public static function paths(): array
    {
        return [];
    }
}
