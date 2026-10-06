<?php

namespace Database\Seeders\Scenarios;

use App\Enums\ResourceType;
use App\Mock\Scenarios\ScenarioKit;

/** Global reference data: real ISO 3166 country codes, invented DPI catalogue. */
final class ReferenceData
{
    private const COUNTRIES = [
        'SA' => 'Saudi Arabia', 'AE' => 'United Arab Emirates', 'BH' => 'Bahrain', 'KW' => 'Kuwait', 'OM' => 'Oman',
        'QA' => 'Qatar', 'EG' => 'Egypt', 'JO' => 'Jordan', 'GB' => 'United Kingdom', 'US' => 'United States',
        'DE' => 'Germany', 'FR' => 'France', 'IN' => 'India', 'PK' => 'Pakistan', 'TR' => 'Türkiye',
    ];

    private const DPI_CATEGORIES = [1 => 'Instant messaging', 2 => 'Streaming media', 3 => 'File sharing', 4 => 'Productivity', 5 => 'Gaming'];

    private const DPI_APPLICATIONS = [
        101 => 'Messaging app', 102 => 'Video calls', 201 => 'Video streaming', 202 => 'Music streaming',
        301 => 'Cloud storage', 401 => 'Office suite', 402 => 'Email', 501 => 'Online games',
    ];

    public static function build(ScenarioKit $kit): void
    {
        foreach (self::COUNTRIES as $code => $name) {
            $kit->add(ResourceType::Country, null, $code, ['code' => $code, 'name' => $name]);
        }
        foreach (self::DPI_CATEGORIES as $id => $name) {
            $kit->add(ResourceType::DpiCategory, null, (string) $id, ['id' => $id, 'name' => $name]);
        }
        foreach (self::DPI_APPLICATIONS as $id => $name) {
            $kit->add(ResourceType::DpiApplication, null, (string) $id, ['id' => $id, 'name' => $name]);
        }
    }
}
