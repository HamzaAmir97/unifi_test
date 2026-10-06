<?php

namespace App\Console\Commands;

use App\Mock\Unverified;
use Illuminate\Console\Command;
use ReflectionClass;

/**
 * FR-025 / SC-006: every assumption made where the collection is silent, read by reflection
 * from App\Mock\Unverified, so the list cannot drift from the code.
 */
class ListUnverified extends Command
{
    protected $signature = 'mock:unverified {--json : Machine-readable output}';

    protected $description = 'List every UNVERIFIED assumption with its gap number';

    /** @return list<array{gap: string, name: string, value: string, note: string}> */
    public static function register(): array
    {
        $rows = [];
        foreach ((new ReflectionClass(Unverified::class))->getReflectionConstants() as $constant) {
            $doc = trim(preg_replace('#^/\*\*|\*/$#', '', (string) $constant->getDocComment()));
            preg_match('/^(G\d+)\s*[—-]\s*(.*)$/su', $doc, $m);
            $rows[] = [
                'gap' => $m[1] ?? '?',
                'name' => $constant->getName(),
                'value' => json_encode($constant->getValue(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'note' => trim(preg_replace('/\s+/', ' ', $m[2] ?? $doc)),
            ];
        }
        usort($rows, fn ($a, $b) => [(int) substr($a['gap'], 1), $a['name']] <=> [(int) substr($b['gap'], 1), $b['name']]);

        return $rows;
    }

    public function handle(): int
    {
        $rows = self::register();

        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->table(['Gap', 'Constant', 'Value', 'Why'], array_map(fn ($r) => [$r['gap'], $r['name'], mb_strimwidth($r['value'], 0, 48, '…'), mb_strimwidth($r['note'], 0, 70, '…')], $rows));
        $this->info(count($rows).' unverified assumptions. They go down only as evidence arrives (D4: the official OpenAPI file or an approved read-only probe).');

        return self::SUCCESS;
    }
}
