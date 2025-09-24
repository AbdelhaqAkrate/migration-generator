<?php

namespace MigraVendor\MigrationGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StructureBuilderCommand extends Command
{
    protected $signature = 'make:structure {table}';
    protected $description = 'Create a Model, Repository, Manager, and Service for a given table';

    public function handle()
    {
        $tableName = $this->argument('table');
        $className = $this->generateClassName($tableName);
        $folderName = $this->generateFolderName($className);

        $this->generateFile('Model', $className, $folderName, $tableName);
        $this->generateFile('Repository', $className . 'Repository', $folderName, $tableName);
        $this->generateFile('Manager', $className . 'Manager', $folderName, $tableName);
        $this->generateFile('Service', $className . 'Service', $folderName, $tableName);

        $this->info("Structure for table {$tableName} created successfully!");
    }

    protected function generateClassName($tableName)
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $tableName)));
    }

    protected function generateFolderName($className)
    {
        return $className;
    }

    protected function getStubContent($type, $className, $folderName, $tableName)
    {
        $stubPath = __DIR__ . '/../stubs/' . $type . '.stub';
        $columns = Schema::getColumnListing($tableName);
        $columnDefinitions = $this->generateColumnDefinitions($columns);
        $getterMethods = $this->generateGetterMethods($columns);

        $importLine = '';
        $parentClass = '';

        if ($type === 'Model') {
            [$importLine, $parentClass] = $this->getModelParentClass();
            $importLine = "use {$importLine};\n";
        }

        $extendsLine = $parentClass ? " extends {$parentClass}" : '';

        return str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ table }}', '{{ extendsLine }}', '{{ importLine }}', '{{ columnDefinitions }}', '{{ getterMethods }}'],
            ["App\\" . Str::plural($type) . "\\{$folderName}", $className, $tableName, $extendsLine, $importLine, $columnDefinitions, $getterMethods],
            file_get_contents($stubPath)
        );
    }
    protected function generateFile($type, $className, $folderName, $tableName)
    {
        $directory = app_path(Str::plural($type) . "/{$folderName}");

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filePath = "{$directory}/{$className}.php";
        $content = $this->getStubContent($type, $className, $folderName, $tableName);

        File::put($filePath, $content);
        $this->info("{$type} created at: {$filePath}");
    }

    protected function getModelParentClass()
    {
        if (class_exists('App\Models\AbstractModel')) {
            return ['App\Models\AbstractModel', 'AbstractModel'];
        }

        if (class_exists('App\Models\Model')) {
            return ['App\Models\Model', 'Model'];
        }

        return ['Illuminate\Database\Eloquent\Model', 'Model'];
    }

    protected function generateColumnDefinitions(array $columns)
    {
        $declaration = '';
        foreach ($columns as $column) {
            $declaration .= $this->generateColumnDefinitionFromStub($column);
        }
        return $declaration;
    }

    protected function generateColumnDefinitionFromStub($column)
    {
        $variableStubPath = __DIR__ . '/../stubs/variable.stub';
        $variableStub = file_get_contents($variableStubPath);

        $constantName = strtoupper($column) . '_COLUMN';
        return str_replace(
            ['{{ constantName }}', '{{ columnName }}'],
            [$constantName, $column],
            $variableStub
        );
    }

    protected function generateGetterMethods(array $columns)
    {
        $methods = '';
        foreach ($columns as $column) {
            $methods .= $this->generateGetterMethodFromStub($column);
        }
        return $methods;
    }

    protected function generateGetterMethodFromStub($column)
    {
        $getterStubPath = __DIR__ . '/../stubs/getter.stub';
        $getterStub = file_get_contents($getterStubPath);

        $methodName = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $column)));
        $constantName = strtoupper($column) . '_COLUMN';

        return str_replace(
            ['{{ methodName }}', '{{ constantName }}'],
            [$methodName, $constantName],
            $getterStub
        );
    }
}
