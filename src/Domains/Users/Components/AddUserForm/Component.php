<?php
declare(strict_types=1);

namespace Domains\Users\Components\AddUserForm;

use Lilly\View\TemplateRenderer;

final class Component
{
    public function render(Props $props): string
    {
        $renderer = new TemplateRenderer();

        return $renderer->render(__DIR__ . '/template.html', [
            'actionPath' => $props->actionPath,
            'notice' => $props->notice,
        ]);
    }
}
