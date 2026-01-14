<?php

namespace App\Services\Contracts;

interface ProfileServiceInterface
{
    public function update(array $data): array;
}
