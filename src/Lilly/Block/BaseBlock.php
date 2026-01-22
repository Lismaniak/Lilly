<?php
declare(strict_types=1);

namespace Lilly\Block;

abstract class BaseBlock implements Block
{
    public function children(): array
    {
        return [];
    }

    public function layoutConstraints(): LayoutConstraints
    {
        return new LayoutConstraints();
    }

    public function hydrate(): ?HydrationPayload
    {
        return null;
    }

    protected function renderChildren(): string
    {
        $html = '';

        foreach ($this->children() as $child) {
            $html .= $child->renderHtml();
        }

        return $html;
    }
}
