<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Schema\Builder\ClassScanner;

use PhpToken;

class ClassScanner
{
    public function __construct(private readonly string $filePath)
    {
    }

    /** @return list<class-string> */
    public function findClasses(): array
    {
        /** @var list<class-string> $classes */
        $classes = [];
        $namespace = '';

        $fileContents = file_get_contents($this->filePath);
        if ($fileContents === false) {
            return [];
        }

        $tokens = PhpToken::tokenize($fileContents);

        $tokensCount = count($tokens);

        for ($i = 0; $i < $tokensCount; $i++) {
            $tokenName = $tokens[$i]->getTokenName();

            switch ($tokenName) {
                case 'T_NAMESPACE':
                    for ($j = $i + 1; $j < $tokensCount; $j++) {
                        if ($tokens[$j]->getTokenName() === 'T_NAME_QUALIFIED') {
                            $namespace = $tokens[$j]->text;
                            break;
                        }
                    }

                    break;
                case 'T_CLASS':
                    for ($j = $i + 1; $j < $tokensCount; $j++) {
                        if ($tokens[$j]->getTokenName() === 'T_WHITESPACE') {
                            continue;
                        }

                        if ($tokens[$j]->getTokenName() !== 'T_STRING') {
                            break;
                        }

                        /** @var class-string $className */
                        $className = $namespace . '\\' . $tokens[$j]->text;

                        $classes[] = $className;
                    }

                    break;
                default:
                    continue 2;
            }
        }

        return $classes;
    }
}
