<?php

namespace MigraVendor\MigrationGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateMigrationCommand extends Command
{
    protected $signature = 'generate:migration';
    protected $description = 'Generate a migration interactively';

    public function handle()
    {
        $tableName = $this->ask('Enter the table name');

        $columns = [];
        while (true) {
            $columnName = $this->ask('Enter column name (or press enter to finish)');
            if (!$columnName) break;

            $columnType = $this->choice('Select column type', ['string', 'integer', 'boolean', 'text', 'date', 'timestamps'], 0);
            $isUnique = $this->confirm('Should it be unique?', false);
            $isForeign = $this->confirm('Is it a foreign key?', false);
            $foreignTable = $isForeign ? $this->ask('Enter referenced table') : null;
            $foreignColumn = $isForeign ? $this->ask('Enter referenced column', 'id') : null;

            $columns[] = compact('columnName', 'columnType', 'isUnique', 'isForeign', 'foreignTable', 'foreignColumn');
        }

        $this->generateMigration($tableName, $columns);
    }

    protected function generateMigration($tableName, $columns)
    {
        $migrationName = 'create_' . $tableName . '_table';
        $fileName = date('Y_m_d_His') . "_{$migrationName}.php";
        $path = database_path("migrations/{$fileName}");

        $stub = File::get(config('migration-generator.default_stub'));
        $stub = str_replace(['{{tableName}}', '{{columns}}'], [$tableName, $this->generateColumns($columns)], $stub);

        File::put($path, $stub);

        $this->info("Migration created: {$fileName}");
    }

    protected function generateColumns($columns)
    {
        $result = "";
        foreach ($columns as $column) {
            $line = "\$table->{$column['columnType']}('{$column['columnName']}')";
            if ($column['isUnique']) $line .= "->unique()";
            if ($column['isForeign']) $line .= ";\n\t\t\t\$table->foreign('{$column['columnName']}')->references('{$column['foreignColumn']}')->on('{$column['foreignTable']}')";
            $line .= ";";
            $result .= "\t\t\t{$line}\n";
        }
        return $result;
    }
}
