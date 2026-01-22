<?php
declare(strict_types=1);

namespace App\Blocks;

use Lilly\Block\BaseBlock;
use Lilly\Block\Block;

final class BodyBlock extends BaseBlock
{
    /**
     * @param list<Block> $children
     */
    public function __construct(
        private readonly array $children = [],
        private readonly string $extraHtml = ''
    ) {
    }

    public function renderHtml(): string
    {
        $children = $this->renderChildren();

        return <<<HTML
<body>
{$children}
{$this->extraHtml}
</body>
HTML;
    }

    public function children(): array
    {
        return $this->children;
    }
}
