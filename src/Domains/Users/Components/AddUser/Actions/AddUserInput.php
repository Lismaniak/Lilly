<?php
declare(strict_types=1);

namespace Domains\Users\Components\AddUser\Actions;

use Lilly\Validation\ArrayValidator;

final readonly class AddUserInput
{
    public string $name;

    public function __construct(string $name)
    {
        $data = ArrayValidator::map(
            ['name' => $name],
            ['name' => ['required', 'string', 'max:255', 'non_empty']]
        );

        $this->name = $data['name'];
    }
}
