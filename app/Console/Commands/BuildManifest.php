<?php

namespace App\Console\Commands;

use App\Mock\Manifest\Manifest;
use App\Mock\Manifest\ManifestBuilder;
use Illuminate\Console\Command;

class BuildManifest extends Command
{
    protected $signature = 'mock:build-manifest';

    protected $description = 'Generate resources/manifest.json from the sanitized collection';

    public function handle(ManifestBuilder $builder): int
    {
        $collection = json_decode(file_get_contents(base_path(ImportCollection::TARGET)), true, flags: JSON_THROW_ON_ERROR);
        $manifest = $builder->build($collection);

        file_put_contents(Manifest::path(), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

        $entries = collect($manifest['entries']);
        $this->info(sprintf(
            'Written resources/manifest.json: %d entries, %d lists, %d truncated examples, %d tables without a filter parameter.',
            $entries->count(),
            $entries->where('isList', true)->count(),
            $entries->where('flags.truncatedExample', true)->count(),
            $entries->where('flags.tableWithoutParameter', true)->count(),
        ));

        return self::SUCCESS;
    }
}
