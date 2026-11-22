<?php

namespace App\Mcp\Contracts;

/** Read-only context an AI client can load, addressed by a URI. */
interface Resource
{
    public function uri(): string;

    public function name(): string;

    public function description(): string;

    public function mimeType(): string;

    public function read(): string;
}
