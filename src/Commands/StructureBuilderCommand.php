<?php

namespace MigraVendor\MigrationGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StructureBuilderCommand extends Command
{
    protected $signature = 'make:structure {table} {--path=}';
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

    private function generateClassName($tableName)
    {
        return Str::studly(Str::singular($tableName));
    }

    private function generateFolderName($className)
    {
        return $className;
    }

    private function getStubContent($type, $className, $folderName, $tableName)
    {
        $workPath = $this->getWorkPath();
        $nameSpaceBase = trim(str_replace(base_path('app') . DIRECTORY_SEPARATOR, '', $workPath), DIRECTORY_SEPARATOR);
        $nameSpaceBase = str_replace(['/', '\\'], '\\', $nameSpaceBase);


        $stubPath = __DIR__ . '/../stubs/' . $type . '.stub';
        $columns = Schema::getColumnListing($tableName);
        $columns = array_filter($columns, function ($column) {
            return !in_array($column, ['id', 'created_at', 'updated_at', 'deleted_at']);
        });

        $columnDefinitions = $this->generateColumnDefinitions($columns);
        $getterMethods = $this->generateGetterMethods($columns);

        $importLine = '';
        $parentClass = '';

        [$parentFqn, $parentClass] = $this->getParentClass($type);

        if ($parentFqn) {
            $importLine = "use {$parentFqn};\n";
        }

        $extendsLine = $parentClass ? " extends {$parentClass}" : '';


        return str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ table }}', '{{ extendsLine }}', '{{ importLine }}', '{{ columnDefinitions }}', '{{ getterMethods }}'],
            [
                "App\\{$nameSpaceBase}\\" . Str::plural($type) . "\\{$folderName}",
                $className,
                $tableName,
                $extendsLine,
                $importLine,
                $columnDefinitions,
                $getterMethods
            ],
            file_get_contents($stubPath)
        );
    }
    private function generateFile($type, $className, $folderName, $tableName)
    {
        $workPath = $this->getWorkPath();
        $directory = $workPath . '/' . Str::plural($type) . "/{$folderName}";

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filePath = "{$directory}/{$className}.php";
        $content = $this->getStubContent($type, $className, $folderName, $tableName);

        File::put($filePath, $content);
        $this->info("{$type} created at: {$filePath}");
    }

    private function getParentClass($type)
    {
        if ($type === 'Model') {
            $workPath = $this->getWorkPath();
            $modelDir = $workPath . '/Models';

            foreach (['AbstractModel', 'Model'] as $file) {
                $modelFile = $modelDir . "/{$file}.php";
                if (File::exists($modelFile)) {
                    $relativePath = trim(str_replace(app_path() . DIRECTORY_SEPARATOR, '', $modelFile), DIRECTORY_SEPARATOR);
                    $class = str_replace(['/', '\\'], '\\', $relativePath);
                    $class = str_replace('.php', '', $class);
                    return ["App\\{$class}", $file];
                }
            }

            return ['Illuminate\Database\Eloquent\Model', 'Model'];
        }


        if (in_array($type, ['Repository', 'Manager', 'Service'])) {
            return $this->findBaseClass($type);
        }

        return [null, null];
    }

    private function generateColumnDefinitions(array $columns)
    {
        $declaration = '';
        foreach ($columns as $column) {
            $declaration .= $this->generateColumnDefinitionFromStub($column);
        }
        return $declaration;
    }

    private function generateColumnDefinitionFromStub($column)
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

    private function generateGetterMethods(array $columns)
    {
        $methods = '';
        foreach ($columns as $column) {
            $methods .= $this->generateGetterMethodFromStub($column);
        }
        return $methods;
    }

    private function generateGetterMethodFromStub($column)
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

    private function findBaseClass(string $type): array
    {
        $directory = $this->getWorkPath() . '/' . Str::plural($type);
        $baseFile  = $directory . '/' . $type . '.php';

        if (File::exists($baseFile)) {
            $relativePath = str_replace(app_path() . DIRECTORY_SEPARATOR, '', $baseFile);
            $class = str_replace(['/', '\\'], '\\', $relativePath);
            $class = str_replace('.php', '', $class);

            $fqn = "App\\{$class}";
            $short = $type;

            return [$fqn, $short];
        }

        return [null, null];
    }

    private function getWorkPath(): string
    {
        $projectPath = $this->option('path');

        if (!$projectPath) {
            return app_path();
        }

        $appFolders = scandir(app_path());
        foreach ($appFolders as $folder) {
            if (strcasecmp($folder, $projectPath) === 0 && is_dir(app_path($folder))) {
                return app_path($folder);
            }
        }

        $this->error("The path '{$projectPath}' does not exist");
        exit(1);
    }
}
