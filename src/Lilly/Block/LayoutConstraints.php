<?php
declare(strict_types=1);

namespace Lilly\Block;

final readonly class LayoutConstraints
{
    public function __construct(
        public bool $fullWidth = false,
        public ?int $fixedHeight = null,
        public ?int $columnSpan = null,
        public ?int $rowSpan = null
    ) {
    }

    public static function fullWidth(?int $fixedHeight = null): self
    {
        return new self(fullWidth: true, fixedHeight: $fixedHeight);
    }
}
