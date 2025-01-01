<?php

declare(strict_types=1);

namespace Shadow\Kernel;

use Shared\MimimalCmsConfig;

/**
 * View class for rendering and displaying templates.
 * 
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
class View implements ViewInterface
{
    public string $renderCache = '';

    public function render(): void
    {
        echo $this->renderCache;
    }

    public function getRenderCache(): string
    {
        return $this->renderCache;
    }

    public function set(string $viewTemplateFile, ?array $valuesArray = null): static
    {
        if (is_array($valuesArray)) {
            if (array_is_list($valuesArray)) {
                throw new \InvalidArgumentException('The passed array must be an associative array or an object.');
            }

            extract($this->sanitizeArray($valuesArray));
        }

        $viewTemplateFile = "/" . ltrim($viewTemplateFile, "/");
        $filePath = MimimalCmsConfig::$viewsDir . $viewTemplateFile;
        if (file_exists($filePath . '.php')) {
            $filePath .= '.php';
        } elseif (file_exists($filePath . '.html')) {
            $filePath .= '.html';
        } else {
            throw new \InvalidArgumentException('Could not find template file: ' . $viewTemplateFile);
        }

        ob_start();
        include $filePath;
        $this->renderCache .= ob_get_clean();

        return $this;
    }

    /**
     * Check if a view template file exists.
     *
     * @param string $viewTemplateFile Path of the view template file to check.
     * @return bool                    Returns true if the file exists, false otherwise.
     */
    public function exists(string $viewTemplateFile): bool
    {
        $viewTemplateFile = "/" . ltrim($viewTemplateFile, "/");
        $filePath = MimimalCmsConfig::$viewsDir . $viewTemplateFile;
        if (file_exists($filePath . '.php')) {
            return true;
        } elseif (file_exists($filePath . '.html')) {
            return true;
        }
        return false;
    }

    public function make(string|ViewInterface $viewTemplateFile, array|null $valuesArray = null): static
    {
        if ($viewTemplateFile instanceof ViewInterface) {
            $this->renderCache .= $viewTemplateFile->getRenderCache();
        } else {
            $this->set($viewTemplateFile, $valuesArray);
        }

        return $this;
    }

    /**
     * Sanitizes an array of values recursively to prevent XSS attacks.
     *
     * @param array $array Array of values to sanitize.
     * @return array       The sanitized array.
     */
    protected function sanitizeArray(array $input): array
    {
        $output = [];
        foreach ($input as $key => $value) {
            if (substr((string) $key, 0, 1) === '_') {
                $output[$key] = $value;
                continue;
            }

            if ($value instanceof ViewInterface) {
                $output[$key] = $value->getRenderCache();
                continue;
            }

            if (is_array($value)) {
                $output[$key] = $this->sanitizeArray($value);
            } elseif (!$value instanceof \UnitEnum && is_object($value)) {
                $output[$key] = $this->sanitizeObject($value);
            } elseif (is_string($value)) {
                $output[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            } else {
                $output[$key] = $value;
            }
        }

        return $output;
    }

    /**
     * Sanitizes an object of values recursively to prevent XSS attacks.
     *
     * @param object $input Array of values to sanitize.
     * @return object       The sanitized object.
     */
    protected function sanitizeObject(object $input): object
    {
        foreach ($input as $key => $value) {
            if (substr((string) $key, 0, 1) === '_') {
                continue;
            }

            if ($value instanceof ViewInterface) {
                $input->$key = $value->getRenderCache();
            } elseif (is_array($value)) {
                $input->$key = $this->sanitizeArray($value);
            } elseif (!$value instanceof \UnitEnum && is_object($value)) {
                $input->$key = $this->sanitizeObject($value);
            } elseif (is_string($value)) {
                $input->$key = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        }

        return $input;
    }
}
