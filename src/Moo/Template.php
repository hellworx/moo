<?php

namespace Moo;

class Template extends Extendable
{
    protected string $lookupDir;

    /** @var array<string, mixed> */
    protected array $defaultContext;

    /**
     * @param array<string, mixed> $defaultContext
     */
    public function __construct(string $lookupDir, array $defaultContext = [])
    {
        $this->lookupDir = $lookupDir;
        $this->defaultContext = $defaultContext;
    }

    /**
     * @param array<string, mixed>|null $context
     */
    public function render(string $script, ?array $context = null): string
    {
        $file = $this->resolve($script);
        $vars = isset($context) ? array_merge($this->defaultContext, $context) : $this->defaultContext;

        ob_start();
        try {
            $this->evaluate($file, $vars);
        } catch (\Throwable $exc) {
            ob_end_clean();
            throw $exc;
        }
        return ob_get_clean();
    }

    protected function resolve(string $script): string
    {
        $base = realpath($this->lookupDir);
        if ($base === false) {
            throw new \RuntimeException('Template directory not found: ' . $this->lookupDir);
        }
        $base = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $file = realpath($base . $script);
        if ($file === false || !str_starts_with($file, $base)) {
            throw new \RuntimeException('Template not found: ' . $script);
        }
        return $file;
    }

    /**
     * @param array<string, mixed> $__vars
     */
    protected function evaluate(string $__file, array $__vars): void
    {
        extract($__vars, EXTR_SKIP);
        include $__file;
    }
}
