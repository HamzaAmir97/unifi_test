<?php

namespace Tests\Feature;

use App\Console\Commands\ListUnverified;
use App\Mock\Unverified;
use ReflectionClass;
use Tests\TestCase;

/** FR-025 / SC-006: the register is the Unverified class, complete, and every entry names its gap. */
class ListUnverifiedTest extends TestCase
{
    public function test_the_register_lists_every_constant(): void
    {
        $constants = array_keys((new ReflectionClass(Unverified::class))->getConstants());
        $listed = array_column(ListUnverified::register(), 'name');

        sort($constants);
        sort($listed);
        $this->assertSame($constants, $listed);
    }

    public function test_every_entry_names_a_gap_between_g1_and_g12(): void
    {
        foreach (ListUnverified::register() as $row) {
            $this->assertMatchesRegularExpression('/^G([1-9]|1[0-2])$/', $row['gap'], $row['name']);
            $this->assertNotSame('', $row['note'], $row['name']);
        }
    }

    public function test_g11_and_g12_are_covered(): void
    {
        $gaps = array_unique(array_column(ListUnverified::register(), 'gap'));

        foreach (['G1', 'G2', 'G6', 'G7', 'G8', 'G10', 'G11', 'G12'] as $gap) {
            $this->assertContains($gap, $gaps);
        }
    }

    public function test_the_command(): void
    {
        $this->artisan('mock:unverified')->assertSuccessful();
        $this->artisan('mock:unverified', ['--json' => true])->assertSuccessful();
    }
}
