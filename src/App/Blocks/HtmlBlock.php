<?php
declare(strict_types=1);

namespace App\Blocks;

use Lilly\Block\BaseBlock;

final class HtmlBlock extends BaseBlock
{
    public function __construct(
        private readonly HeadBlock $head,
        private readonly BodyBlock $body,
        private readonly string $lang = 'en'
    ) {
    }

    public function renderHtml(): string
    {
        $lang = htmlspecialchars($this->lang, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<!doctype html>
<html lang="{$lang}">
{$this->head->renderHtml()}
{$this->body->renderHtml()}
</html>
HTML;
    }
}
