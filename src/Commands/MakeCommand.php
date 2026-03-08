<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use OmniTerm\HasOmniTerm;

class MakeCommand extends GeneratorCommand
{
    use HasOmniTerm;

    protected $signature = 'elastic:make {name : The name of the Elasticsearch model}';

    protected $description = 'Create a new Elasticsearch model';

    protected $type = 'Model';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));

        $namespace = $this->resolveNamespace($name);
        $class = class_basename(str_replace('/', '\\', $name));
        $fullClass = $namespace.'\\'.$class;
        $path = $this->getModelPath($name);

        if (file_exists($path)) {
            $this->newLine();
            $this->omni->statusError('Model already exists', $fullClass);
            $this->newLine();

            return self::FAILURE;
        }

        $this->makeDirectory($path);

        $stub = $this->buildStub($namespace, $class);
        $this->files->put($path, $stub);

        $this->newLine();
        $this->omni->statusSuccess('Model created', $fullClass);
        $this->omni->tableRow('Path', str_replace(base_path().'/', '', $path));
        $this->newLine();

        return self::SUCCESS;
    }

    protected function getStub(): string
    {
        return __DIR__.'/../../resources/stubs/ElasticModel.php.stub';
    }

    private function resolveNamespace(string $name): string
    {
        $parts = explode('/', str_replace('\\', '/', $name));
        array_pop($parts);

        $sub = implode('\\', $parts);

        return $sub
            ? 'App\\Models\\'.$sub
            : 'App\\Models';
    }

    private function getModelPath(string $name): string
    {
        $name = str_replace('\\', '/', $name);

        return app_path('Models/'.$name.'.php');
    }

    private function buildStub(string $namespace, string $class): string
    {
        $stub = $this->files->get($this->getStub());

        return str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [$namespace, $class],
            $stub
        );
    }
}
