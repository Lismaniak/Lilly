<?php
declare(strict_types=1);

namespace Domains\Users\Blocks;

use Domains\Users\Components\AddUserForm\Component;
use Domains\Users\Components\AddUserForm\Props;
use Lilly\Block\BaseBlock;
use Lilly\Block\LayoutConstraints;

final class AddUserFormBlock extends BaseBlock
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
