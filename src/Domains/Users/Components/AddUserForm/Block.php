<?php
declare(strict_types=1);

namespace Domains\Users\Components\AddUserForm;

use Lilly\Block\BaseBlock;
use Lilly\Block\LayoutConstraints;

final class Block extends BaseBlock
{
    public function __construct(
        private readonly Props $props
    ) {
    }

    public function renderHtml(): string
    {
        $component = new Component();

        return $component->render($this->props);
    }

    public function layoutConstraints(): LayoutConstraints
    {
        return LayoutConstraints::fullWidth();
    }
}
