<?php

namespace MigraVendor\MigrationGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateMigrationCommand extends Command
{
    protected $signature = 'generate:migration';
    protected $description = 'Generate a migration interactively';

    protected array $columnTypes = [
        'bigIncrements', 'bigInteger', 'boolean', 'char', 'date', 'dateTime', 'double', 
        'float', 'increments', 'integer', 'smallInteger', 'string', 'text', 'time', 'timestamp',
        'unsignedBigInteger', 'unsignedInteger', 'unsignedTinyInteger', 'uuid', 'year'
    ];

    protected array $foreignKeyTypes = [
        'unsignedBigInteger', 'unsignedInteger', 'unsignedSmallInteger',
        'unsignedMediumInteger', 'unsignedTinyInteger', 'uuid'
    ];

    public function handle()
    {
        $tableName = $this->ask('Enter the table name');

        $hasId = $this->confirm('Should the table have an `id` column (primary key)?', true);
        
        $columns = $hasId ? [['columnName' => 'id', 'columnType' => 'id']] : [];

        while (true) {
            $columnName = $this->ask('Enter column name (or press enter to finish)');
            if (!$columnName) break;

            $columnType = $this->askColumnType();

            $isNullable = $this->confirm('Should it be nullable?', false);
            $isUnique = $this->confirm('Is it unique?', false);
            $isForeign = in_array($columnType, $this->foreignKeyTypes) ? $this->confirm('Is it a foreign key?', false) : false;
            $foreignTable = $isForeign ? $this->ask('Referenced table') : null;
            $foreignColumn = $isForeign ? $this->ask('Referenced column', 'id') : null;
            $onDelete = $isForeign ? $this->choice('ON DELETE action', ['cascade', 'set null', 'restrict', 'no action'], 0) : null;
            $onUpdate = $isForeign ? $this->choice('ON UPDATE action', ['cascade', 'set null', 'restrict', 'no action'], 0) : null;

            $columns[] = [
                'columnName' => $columnName,
                'columnType' => $columnType,
                'isNullable' => $isNullable ?: false,
                'isUnique' => $isUnique ?: false,
                'isForeign' => $isForeign ?: false,
                'foreignTable' => $foreignTable ?? '',
                'foreignColumn' => $foreignColumn ?? '',
                'onDelete' => $onDelete ?? '',
                'onUpdate' => $onUpdate ?? ''
            ];
        }

        $this->generateMigration($tableName, $columns);
    }

    protected function askColumnType(): string
    {
        while (true) {
            $inputType = strtolower($this->ask('Enter column type (ex: string, integer, etc.)'));

            if (in_array($inputType, $this->columnTypes)) {
                return $inputType;
            }

            $suggestions = $this->findClosestMatches($inputType);
            if (!empty($suggestions)) {
                $selected = $this->choice("Did you mean:", $suggestions, 0);
                return $selected;
            }

            $this->error("Invalid column type. Please try again.");
        }
    }

    protected function findClosestMatches(string $inputType): array
    {
        $pregInput = strtolower(preg_replace('/[^a-zA-Z]/', '', $inputType));
        $matches = [];

        foreach ($this->columnTypes as $type) {
            $levDistance = levenshtein($pregInput, strtolower($type));
            if ($levDistance <= 2) {
                $matches[$type] = $levDistance;
            }
        }

        asort($matches);
        return array_keys($matches);
    }

    protected function generateMigration($tableName, $columns)
    {
        $migrationName = 'create_' . $tableName . '_table';
        $fileName = date('Y_m_d_His') . "_{$migrationName}.php";
        $path = database_path("migrations/{$fileName}");

        $stub = File::get(config('migration-generator.default_stub'));
        $stub = str_replace(['{{tableName}}', '{{columns}}'], [$tableName, $this->generateMigrationBody($columns)], $stub);

        File::put($path, $stub);

        $this->info("Migration created: {$fileName}");
    }

    protected function generateMigrationBody($columns)
    {
        $result = "";
        foreach ($columns as $column) {
            $columnName = $column['columnName'];
            $columnType = $column['columnType'];
            $line = "\$table->{$columnType}('{$columnName}')";

            if (isset($column['isNullable']) && $column['isNullable']) {
                $line .= "->nullable()";
            }

            if (isset($column['isUnique']) && $column['isUnique']) {
                $line .= "->unique()";
            }

            if (isset($column['isForeign']) && $column['isForeign'] && !empty($column['foreignTable'])) {
                $foreignTable = $column['foreignTable'];
                $foreignColumn = $column['foreignColumn'] ?? 'id';
                $onDelete = $column['onDelete'];
                $onUpdate = $column['onUpdate'];

                $line .= "; \n\t\t\t\$table->foreign('{$columnName}')->references('{$foreignColumn}')->on('{$foreignTable}')";

                if ($onDelete !== '') {
                    $line .= "->onDelete('{$onDelete}')";
                }

                if ($onUpdate !== '') {
                    $line .= "->onUpdate('{$onUpdate}')";
                }
            }

            $line .= ";";
            $result .= "\t\t\t{$line}\n";
        }
        return $result;
    }
}
