<?php
declare(strict_types=1);

namespace Lilly\Block;

final readonly class HydrationPayload
{
    /**
     * @param array<string, mixed> $props
     */
    public function __construct(
        public string $entrypoint,
        public array $props = [],
        public ?string $islandId = null
    ) {
    }
}
