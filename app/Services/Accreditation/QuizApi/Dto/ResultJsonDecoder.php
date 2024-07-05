<?php

declare(strict_types=1);

namespace App\Services\Accreditation\QuizApi\Dto;

use App\Services\Accreditation\QuizApi\Dto\Question\Contributor;
use App\Services\Accreditation\QuizApi\Dto\Question\Result;
use App\Services\Accreditation\QuizApi\Dto\Question\Source;
use ReflectionClass;
use ReflectionException;
use Shared\Exceptions\ValidationException;

class ResultJsonDecoder
{
    private string $class;
    private array $classMap;

    public function __construct()
    {
        $this->class = Result::class;

        $this->classMap = [
            'contributor' => Contributor::class,
            'source' => Source::class,
        ];
    }

    /**
     * @param string $json
     * @param string $className
     * @return mixed
     * @throws ReflectionException
     */
    public function decode(string $json): mixed
    {
        $data = json_decode($json, true);

        try {
            return array_map(fn ($result) =>  $this->instantiateClass($result, $this->class), $data);
        } catch (\Throwable $e) {
            throw new ValidationException("Error: " . $e->getMessage());
        }
    }

    /**
     * @param array $data
     * @param string $className
     * @return mixed
     * @throws ReflectionException
     */
    private function instantiateClass(array $data, string $className): mixed
    {
        $reflector = new ReflectionClass($className);
        $constructor = $reflector->getConstructor();
        $params = $constructor->getParameters();
        $args = [];

        foreach ($params as $param) {
            $name = $param->getName();
            $type = $param->getType();

            if (!$type || !isset($data[$name])) {
                $args[] = $data[$name] ?? null;
                continue;
            }

            $typeName = $type->getName();

            if (isset($this->classMap[$name])) {
                $typeName = $this->classMap[$name];
            }

            if (class_exists($typeName)) {
                if (is_array($data[$name])) {
                    if ($this->isAssocArray($data[$name])) {
                        $args[] = $this->instantiateClass($data[$name], $typeName);
                    } else {
                        $subInstances = [];
                        foreach ($data[$name] as $subData) {
                            $subInstances[] = $this->instantiateClass($subData, $typeName);
                        }
                        $args[] = $subInstances;
                    }
                } else {
                    $args[] = $this->instantiateClass($data[$name], $typeName);
                }
            } else {
                $args[] = $data[$name];
            }
        }

        return $reflector->newInstanceArgs($args);
    }

    /**
     * Check if array is associative
     *
     * @param array $array
     * @return bool
     */
    private function isAssocArray(array $array): bool
    {
        if ([] === $array) return false;
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
