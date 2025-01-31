<?php

namespace MigraVendor\MigrationGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateMigrationCommand extends Command
{
    protected $signature = 'generate:migration';
    protected $description = 'Generate a migration interactively';

    protected array $columnTypes = [
        'bigIncrements', 'bigInteger', 'binary', 'boolean', 'char', 'date', 'dateTime', 'dateTimeTz', 'decimal',
        'double', 'enum', 'float', 'foreignId', 'geometry', 'geometryCollection', 'id', 'increments', 'integer',
        'ipAddress', 'json', 'jsonb', 'lineString', 'longText', 'macAddress', 'mediumIncrements', 'mediumInteger',
        'mediumText', 'morphs', 'multiLineString', 'multiPoint', 'multiPolygon', 'nullableMorphs', 'nullableTimestamps',
        'point', 'polygon', 'rememberToken', 'set', 'smallIncrements', 'smallInteger', 'softDeletes', 'softDeletesTz',
        'string', 'text', 'time', 'timeTz', 'timestamp', 'timestampTz', 'tinyIncrements', 'tinyInteger',
        'unsignedBigInteger', 'unsignedDecimal', 'unsignedInteger', 'unsignedMediumInteger', 'unsignedSmallInteger',
        'unsignedTinyInteger', 'uuid', 'year'
    ];

    protected array $shortcuts = [
        'n' => 'nullable',
        'u' => 'unique',
        'f' => 'foreign',
        'd' => 'default',
        'c' => 'cascade',
        'r' => 'restrict',
        'no' => 'no action',
    ];

    public function handle()
    {
        $tableName = $this->ask('Enter the table name');

        if (empty($tableName)) {
            $this->error("Table name is required. Please restart the command.");
            return;
        }    

        $hasId = $this->confirm('Should the table have an `id` column?', true);
        $columns = $hasId ? [['columnName' => 'id', 'columnType' => 'id']] : [];

        $this->info("Define columns in the following format:");
        $this->info("column_name:column_type[|n][|u][|f:table:column][|d:value]");
        $this->info("Examples:");
        $this->info("  name:string|n|u");
        $this->info("  user_id:unsignedBigInteger|f:users:id");
        $this->info("  price:decimal(8,2)|d:0.00\n");

        while (true) {
            $columnInput = $this->ask('Enter column definition (or press enter to finish)');
            if (!$columnInput) break;

            $columnData = $this->parseColumnInput($columnInput);
            if (!$columnData) {
                $this->error("Invalid format. Use the correct structure.");
                continue;
            }

            $columns[] = $columnData;
        }

        if (empty($columns)) {
            $this->error("No columns defined. Aborting.");
            return;
        }

        $this->generateMigration($tableName, $columns);
    }

    public function parseColumnInput(string $input): ?array
    {
        $parts = explode('|', $input);
        $columnBase = explode(':', array_shift($parts));

        if (count($columnBase) < 2) {
            return null;
        }

        [$columnName, $columnType] = $columnBase;

        $normalizedType = $this->askForValidColumnType($columnType);
        if (!$normalizedType) {
            return null;
        }

        $isNullable = in_array('nullable', $parts);
        $isUnique = in_array('unique', $parts);

        $isForeign = false;
        $foreignTable = $foreignColumn = null;
        $onDelete = 'no action';
        $onUpdate = 'no action';

        foreach ($parts as $part) {
            if (str_starts_with($part, 'f:')) {
                $foreignParts = explode(':', $part);
                $isForeign = true;
                $foreignTable = $foreignParts[1] ?? null;
                $foreignColumn = $foreignParts[2] ?? 'id';
            }
            if (isset($this->shortcuts[$part])) {
                if ($part === 'c' || $part === 'r' || $part === 'no') {
                    $onDelete = $this->shortcuts[$part];
                    $onUpdate = $this->shortcuts[$part];
                }
            }
        }

        return [
            'columnName' => $columnName,
            'columnType' => $normalizedType,
            'isNullable' => $isNullable,
            'isUnique' => $isUnique,
            'isForeign' => $isForeign,
            'foreignTable' => $foreignTable,
            'foreignColumn' => $foreignColumn,
            'onDelete' => $onDelete,
            'onUpdate' => $onUpdate
        ];
    }

    protected function askForValidColumnType(string $inputType): ?string
    {
        while (!in_array($inputType, $this->columnTypes)) {
            $suggestedType = $this->findClosestMatch($inputType);
    
            if ($suggestedType) {
                $confirmation = $this->ask(
                    "Unknown type '{$inputType}'. Did you mean '{$suggestedType}'? (y/n)"
                );
    
                if (strtolower($confirmation) === 'y') {
                    return $suggestedType;
                }
    
                $inputType = $this->ask("Enter a new type or 'c' to stop adding");
            } else {
                $inputType = $this->ask("Unknown column type '{$inputType}'. Enter a valid type or 'c' to stop adding");
            }
    
            if ($inputType === 'c') {
                return null;
            }
        }
    
        return $inputType;
    }

    protected function findClosestMatch(string $inputType): ?string
    {
        $closestType = null;
        $shortestDistance = null;

        foreach ($this->columnTypes as $type) {
            $distance = levenshtein(strtolower($inputType), strtolower($type));
            if ($shortestDistance === null || $distance < $shortestDistance) {
                $closestType = $type;
                $shortestDistance = $distance;
            }
        }

        return ($shortestDistance !== null && $shortestDistance <= 3) ? $closestType : null;
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
            $line = "\$table->{$column['columnType']}('{$column['columnName']}')";

            if (isset($column['isNullable']) && $column['isNullable']) {
                $line .= "->nullable()";
            }

            if (isset($column['isUnique']) && $column['isUnique']) {
                $line .= "->unique()";
            }

            if (isset($column['default']) && $column['default'] !== null) {
                $defaultValue = is_numeric($column['default']) ? $column['default'] : "'{$column['default']}'";
                $line .= "->default({$defaultValue})";
            }

            if (isset($column['isForeign']) && $column['isForeign']) {
                $line .= "; \n\t\t\t\$table->foreign('{$column['columnName']}')"
                    . "->references('{$column['foreignColumn']}')->on('{$column['foreignTable']}')"
                    . "->onDelete('{$column['onDelete']}')->onUpdate('{$column['onUpdate']}')";
            }

            $line .= ";";
            $result .= "\t\t\t{$line}\n";
        }
        return $result;
    }
}
