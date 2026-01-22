<?php
declare(strict_types=1);

namespace Domains\Users\Components\AddUserForm;

use Lilly\Validation\ArrayValidator;

final readonly class AddUserFormInput
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
