<?php
declare(strict_types=1);

namespace App\Blocks;

use Lilly\Block\BaseBlock;

final class HeadBlock extends BaseBlock
{
    public function __construct(
        private readonly string $title,
        private readonly string $extraHtml = ''
    ) {
    }

    public function renderHtml(): string
    {
        $title = htmlspecialchars($this->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<head>
<meta charset="utf-8">
<title>{$title}</title>
{$this->extraHtml}
</head>
HTML;
    }
}
