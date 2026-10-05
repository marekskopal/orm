<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Builder;

use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Schema\Builder\ClassScanner\ClassScanner;
use MarekSkopal\ORM\Schema\Compiler\SchemaDumper;
use MarekSkopal\ORM\Schema\EntitySchema;
use MarekSkopal\ORM\Schema\Enum\CaseEnum;
use MarekSkopal\ORM\Schema\Schema;
use Nette\Utils\Finder;
use ReflectionClass;

class SchemaBuilder
{
    /** @var list<string> */
    private array $entityPaths = [];

    private CaseEnum $tableCase = CaseEnum::SnakeCase;

    private CaseEnum $columnCase = CaseEnum::SnakeCase;

    public function build(): Schema
    {
        return new Schema($this->getEntitiesSchema());
    }

    /**
     * Builds the schema and writes it, with generated hydrators and extractors, to a PHP file that
     * Schema::fromFile() loads. Run it in a build or deploy step. The file is written atomically,
     * so concurrent requests never read a partial file.
     */
    public function dump(string $path): Schema
    {
        $schema = $this->build();

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Cannot create directory "%s".', $directory));
        }

        $temporaryPath = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temporaryPath, new SchemaDumper()->dump($schema)) === false) {
            throw new \RuntimeException(sprintf('Cannot write schema file "%s".', $temporaryPath));
        }

        if (!rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new \RuntimeException(sprintf('Cannot move schema file to "%s".', $path));
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }

        return $schema;
    }

    public function addEntityPath(string $path): self
    {
        $this->entityPaths[] = $path;

        return $this;
    }

    public function setTableCase(CaseEnum $tableCase): self
    {
        $this->tableCase = $tableCase;

        return $this;
    }

    public function setColumnCase(CaseEnum $columnCase): self
    {
        $this->columnCase = $columnCase;

        return $this;
    }

    /** @return array<class-string,EntitySchema> */
    private function getEntitiesSchema(): array
    {
        $entitiesSchema = [];

        $entitySchemaFactory = new EntitySchemaFactory();

        foreach ($this->entityPaths as $path) {
            $phpFiles = Finder::findFiles($path . '/**/*.php');

            foreach ($phpFiles as $phpFile) {
                $classScanner = new ClassScanner($phpFile->getRealPath());

                foreach ($classScanner->findClasses() as $class) {
                    $reflectionClass = new ReflectionClass($class);
                    $attributes = $reflectionClass->getAttributes(Entity::class);

                    if (count($attributes) === 0) {
                        continue;
                    }

                    $entitiesSchema[$class] = $entitySchemaFactory->create($reflectionClass, $this->tableCase, $this->columnCase);
                }
            }
        }

        return $entitiesSchema;
    }
}
