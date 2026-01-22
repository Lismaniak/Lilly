<?php
declare(strict_types=1);

namespace Domains\Users\Components\AddUser;

final readonly class Props
{
    public function __construct(
        public string $actionPath,
        public ?string $notice = null
    ) {
    }
}
