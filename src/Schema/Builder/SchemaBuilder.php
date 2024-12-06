<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Builder;

use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Schema\Builder\ClassScanner\ClassScanner;
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

    public function addEntityPath(string $path): self
    {
        $this->entityPaths[] = $path;

        return $this;
    }

    public function setTableCase(CaseEnum $tableCase): self
    {
        $this->columnCase = $tableCase;

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

                    $entitiesSchema[$class] = new EntitySchemaFactory()->create($reflectionClass, $this->tableCase, $this->columnCase);
                }
            }
        }

        return $entitiesSchema;
    }
}
