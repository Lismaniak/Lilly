<?php
declare(strict_types=1);

namespace Lilly\View;

final class TemplateRenderer
{
    /**
     * @param array<string, mixed> $props
     */
    public function render(string $templatePath, array $props): string
    {
        $contents = file_get_contents($templatePath);

        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to read template at "%s".', $templatePath));
        }

        $contents = $this->renderConditionals($contents, $props);
        $contents = $this->renderRawTokens($contents, $props);

        return $this->renderEscapedTokens($contents, $props);
    }

    /**
     * @param array<string, mixed> $props
     */
    private function renderConditionals(string $contents, array $props): string
    {
        $pattern = '/\{\{\#(if|unless)\s+([a-zA-Z0-9_.-]+)\s*\}\}(.*?)\{\{\/\1\}\}/s';

        while (preg_match($pattern, $contents)) {
            $contents = preg_replace_callback(
                $pattern,
                function (array $matches) use ($props): string {
                    $type = $matches[1];
                    $key = $matches[2];
                    $block = $matches[3];
                    $value = $this->resolveValue($props, $key);
                    $truthy = $this->isTruthy($value);

                    if ($type === 'unless') {
                        $truthy = !$truthy;
                    }

                    return $truthy ? $block : '';
                },
                $contents
            );
        }

        return $contents;
    }

    /**
     * @param array<string, mixed> $props
     */
    private function renderRawTokens(string $contents, array $props): string
    {
        return preg_replace_callback(
            '/\{\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}\}/',
            function (array $matches) use ($props): string {
                $value = $this->resolveValue($props, $matches[1]);

                return $this->stringify($value);
            },
            $contents
        );
    }

    /**
     * @param array<string, mixed> $props
     */
    private function renderEscapedTokens(string $contents, array $props): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/',
            function (array $matches) use ($props): string {
                $value = $this->resolveValue($props, $matches[1]);

                return htmlspecialchars($this->stringify($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            },
            $contents
        );
    }

    /**
     * @param array<string, mixed> $props
     */
    private function resolveValue(array $props, string $path): mixed
    {
        $segments = explode('.', $path);
        $value = $props;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        return !empty($value);
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }
}
