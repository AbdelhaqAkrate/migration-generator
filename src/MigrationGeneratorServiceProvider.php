<?php

namespace MigraVendor\MigrationGenerator;

use Illuminate\Support\ServiceProvider;

class MigrationGeneratorServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config/migration-generator.php', 'migration-generator');

        $this->commands([
            \MigraVendor\MigrationGenerator\Commands\GenerateMigrationCommand::class,
        ]);
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/migration-generator.php' => config_path('migration-generator.php'),
            __DIR__ . '/stubs/migration.stub' => base_path('stubs/migration.stub'),
        ], 'migration-generator');
    }
}
