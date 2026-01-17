<?php
// src/Lilly/Security/Gate.php

declare(strict_types=1);

namespace Lilly\Security;

use Lilly\Http\Request;

interface Gate
{
    public function name(): string;

    public function authorize(Request $request): PolicyDecision;
}