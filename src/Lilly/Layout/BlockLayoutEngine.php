<?php
declare(strict_types=1);

namespace Lilly\Layout;

use Lilly\Block\Block;

final readonly class BlockLayoutEngine
{
    /**
     * @param array<string, mixed> $viewport
     * @return array<string, array<string, int>>
     */
    public function compute(Block $root, array $viewport): array
    {
        return [];
    }
}
