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
        $pattern = '/\{\{\#(if|unless)\s+([a-zA-Z0-9_.-]+)\s*\}\}|\{\{\/(if|unless)\s*\}\}/';
        $offset = 0;
        $stack = [
            [
                'type' => null,
                'key' => null,
                'buffer' => '',
            ],
        ];

        while (preg_match($pattern, $contents, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $match = $matches[0][0];
            $matchStart = $matches[0][1];
            $matchLength = strlen($match);

            $stack[count($stack) - 1]['buffer'] .= substr($contents, $offset, $matchStart - $offset);

            if (!empty($matches[1][0])) {
                $stack[] = [
                    'type' => $matches[1][0],
                    'key' => $matches[2][0],
                    'buffer' => '',
                ];
            } else {
                $closeType = $matches[3][0];

                if (count($stack) === 1) {
                    $stack[0]['buffer'] .= $match;
                } else {
                    $frame = array_pop($stack);

                    if ($frame['type'] !== $closeType) {
                        $stack[] = $frame;
                        $stack[count($stack) - 1]['buffer'] .= $match;
                    } else {
                        $value = $this->resolveValue($props, (string) $frame['key']);
                        $truthy = $this->isTruthy($value);

                        if ($frame['type'] === 'unless') {
                            $truthy = !$truthy;
                        }

                        if ($truthy) {
                            $stack[count($stack) - 1]['buffer'] .= $frame['buffer'];
                        }
                    }
                }
            }

            $offset = $matchStart + $matchLength;
        }

        $stack[count($stack) - 1]['buffer'] .= substr($contents, $offset);

        while (count($stack) > 1) {
            $frame = array_pop($stack);
            $stack[count($stack) - 1]['buffer'] .= $this->formatOpenTag(
                (string) $frame['type'],
                (string) $frame['key']
            );
            $stack[count($stack) - 1]['buffer'] .= $frame['buffer'];
        }

        return $stack[0]['buffer'];
    }

    private function formatOpenTag(string $type, string $key): string
    {
        return sprintf('{{#%s %s}}', $type, $key);
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
