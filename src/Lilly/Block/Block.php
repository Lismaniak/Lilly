<?php
declare(strict_types=1);

namespace Lilly\Block;

interface Block
{
    public function renderHtml(): string;

    /**
     * @return list<Block>
     */
    public function children(): array;

    public function layoutConstraints(): LayoutConstraints;

    public function hydrate(): ?HydrationPayload;
}
