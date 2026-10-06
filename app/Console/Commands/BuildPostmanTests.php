<?php

namespace App\Console\Commands;

use App\Mock\Postman\PostmanTests;
use Illuminate\Console\Command;

/** Adds the pm.test scripts and the read-only folder to the sanitized collection (idempotent). */
class BuildPostmanTests extends Command
{
    protected $signature = 'mock:postman-tests';

    protected $description = 'Add repeatable pm.test checks and the read-only production folder to the collection';

    public function handle(PostmanTests $tests): int
    {
        $path = base_path(ImportCollection::TARGET);
        $collection = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        file_put_contents($path, json_encode($tests->apply($collection), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        $this->info('Collection updated: pm.test scripts and the folder "'.PostmanTests::READ_ONLY_FOLDER.'".');

        return self::SUCCESS;
    }
}
