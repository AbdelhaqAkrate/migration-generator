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
        $this->info("\n📢 Welcome to the Interactive Migration Generator!");
        $this->info("This tool will help you create Laravel migration files step by step.\n");
        
        $tableName = $this->askForTableName();
        $columns = $this->collectColumnDefinitions();

        if (empty($columns)) {
            $this->error("❌ No columns defined. Aborting.");
            return;
        }

        $this->generateMigration($tableName, $columns);
    }

    private function askForTableName(): string
    {
        do {
            $tableName = $this->ask("Enter the table name:");
            if (empty($tableName)) {
                $this->error("❌ Table name cannot be empty. Please enter a valid name.");
            }
        } while (empty($tableName));
        
        return $tableName;
    }

    private function collectColumnDefinitions(): array
    {
        $columns = [];
        if ($this->confirm('Should the table have an `id` column?', true)) {
            $columns[] = ['columnName' => 'id', 'columnType' => 'id'];
        }

        if ($this->confirm('Should the table support soft deletes?', false)) {
            $columns[] = ['columnName' => 'softDeletes', 'columnType' => 'softDeletes'];
        }

        $this->info("\n📌 Define columns using the following format:");
        $this->info("column_name:column_type[|n][|u][|f:table:column][|d:value]");
        $this->info("Type 'help' for guidance or press enter on an empty line to finish.\n");
        while (true) {
            $columnInput = $this->ask('Enter column definition (or press enter to finish):');
            if (!$columnInput) break;
            if ($columnInput === 'help') {
                $this->displayHelp();
                continue;
            }
            $columnData = $this->parseColumnInput($columnInput);
            if (!$columnData) {
                $this->error("❌ Invalid format. Please follow the instructions.");
                continue;
            }
            
            $columns[] = $columnData;
        }
        
        return $columns;
    }

    public function parseColumnInput(string $input): ?array
    {
        $parts = explode('|', $input);
        $columnBase = explode(':', array_shift($parts));
    
        if (count($columnBase) < 2) {
            return null;
        }
    
        [$columnName, $columnType] = $columnBase;
        $normalizedType = $this->validateColumnType($columnType);
        if (!$normalizedType) {
            return null;
        }
    
        $isNullable = in_array('n', $parts);
        $isUnique = in_array('u', $parts);
    
        $isForeign = false;
        $foreignTable = $foreignColumn = null;
        $onDelete = 'no action';
        $onUpdate = 'no action';
        $defaultValue = null;
        foreach ($parts as $part) {
            if (str_starts_with($part, 'f:')) {
                $foreignParts = explode(':', $part);
                $isForeign = true;
                $foreignTable = $foreignParts[1] ?? null;
                $foreignColumn = $foreignParts[2] ?? 'id';
                if (isset($foreignParts[3]) && isset($this->shortcuts[$foreignParts[3]]) && $foreignParts[3] != 'no' && $foreignParts[3]) {
                    $onDelete = $this->shortcuts[$foreignParts[3]];
                }
                if (isset($foreignParts[4]) && isset($this->shortcuts[$foreignParts[4]]) && $foreignParts[4] != 'no') {
                    $onUpdate = $this->shortcuts[$foreignParts[4]];
                }
            }

            if (str_starts_with($part, 'd:')) {
                $defaultValue = substr($part, 2);
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
            'onUpdate' => $onUpdate,
            'default' => $defaultValue
        ];
    }

    private function validateColumnType(string $type): ?string
    {
        if (!in_array($type, $this->columnTypes)) {
            $suggestedType = $this->findClosestMatch($type);
            if ($suggestedType && $this->confirm("Unknown type '{$type}'. Did you mean '{$suggestedType}'?", true)) {
                return $suggestedType;
            }
            $this->error("❌ Invalid column type '{$type}'. Please enter a valid type.");
            return null;
        }
        return $type;
    }

    private function findClosestMatch(string $inputType): ?string
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

    private function generateMigration($tableName, $columns)
    {
        $migrationName = 'create_' . $tableName . '_table';
        $fileName = date('Y_m_d_His') . "_{$migrationName}.php";
        $path = database_path("migrations/{$fileName}");

        $stub = File::get(config('migration-generator.default_stub'));
        $stub = str_replace(['{{tableName}}', '{{columns}}'], [$tableName, $this->generateMigrationBody($columns)], $stub);

        File::put($path, $stub);

        $this->info("✅ Migration created: {$fileName}");
    }

    private function generateMigrationBody($columns)
    {
        $result = "";
        $softDeletStmt = "";
        foreach ($columns as $column) {
            if ($column['columnType'] === 'softDeletes') {
                $softDeletStmt = "\t\t\t\$table->softDeletes();\n";
                continue;
            }

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
        
        $result .= $softDeletStmt;

        return $result;
    }

    private function displayHelp()
    {
        $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📖 MIGRATION GENERATOR HELP");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $this->info("\n🛠️ Column Type Guide:");

        $columnsPerRow = 4;

        $columnChunks = array_chunk($this->columnTypes, $columnsPerRow);

        $formattedRows = array_map(fn($chunk) => array_pad($chunk, $columnsPerRow, ''), $columnChunks);

        $this->table(array_fill(0, $columnsPerRow, 'Column Type'), $formattedRows);

        $this->info("\n⚡ Shortcut Modifiers:");
        $this->table(['Shortcut', 'Description'], array_map(fn($key, $desc) => [$key, $desc], array_keys($this->shortcuts), $this->shortcuts));

        $this->info("\n📌 Example Column Definitions:");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("  name:string|n|u   (Nullable & Unique string column)");
        $this->info("  user_id:unsignedBigInteger|f:users:id  (Foreign key reference)");
        $this->info("  price:decimal(8,2)|d:0.00  (Decimal with default value)");

        $this->info("\n✅ Use the format: column_name:column_type[|modifiers]");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    }
}
