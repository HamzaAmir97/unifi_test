<?php

namespace Tests\Unit\Presenters;

use App\Mock\Manifest\Manifest;
use App\Mock\Presenters\Presenter;
use App\Mock\Settings\MockSettings;
use App\Models\MockResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PresenterTest extends TestCase
{
    use RefreshDatabase;

    /** A presenter over the client object: required access,id,name,type; optional connectedAt,ipAddress. */
    private function presenter(array $values): Presenter
    {
        return new class(app(Manifest::class), app(MockSettings::class), $values) extends Presenter
        {
            public function __construct($m, $s, private array $v)
            {
                parent::__construct($m, $s);
            }

            protected function fieldsFrom(): array
            {
                return ['get-connected-client-details', ''];
            }

            protected function values(MockResource $resource): array
            {
                return $this->v;
            }
        };
    }

    private const VALUES = ['type' => 'WIRELESS', 'name' => 'Phone', 'id' => 'x', 'access' => [], 'connectedAt' => null, 'ipAddress' => '192.0.2.5'];

    public function test_required_first_then_optional_and_absent_optionals_omitted(): void
    {
        $out = $this->presenter(self::VALUES)->present(new MockResource);

        $this->assertSame(['access', 'id', 'name', 'type', 'ipAddress'], array_keys($out));
    }

    public function test_null_mode_returns_absent_optionals_as_null(): void
    {
        app(MockSettings::class)->put(MockSettings::OPTIONAL_FIELDS, 'null');

        $out = $this->presenter(self::VALUES)->present(new MockResource);

        $this->assertArrayHasKey('connectedAt', $out);
        $this->assertNull($out['connectedAt']);
    }

    public function test_a_missing_required_field_is_detected(): void
    {
        $values = self::VALUES;
        unset($values['access']);

        $this->expectException(LogicException::class);
        $this->presenter($values)->present(new MockResource);
    }
}
