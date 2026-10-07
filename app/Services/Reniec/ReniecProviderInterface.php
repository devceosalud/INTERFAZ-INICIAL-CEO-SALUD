<?php

namespace App\Services\Reniec;

interface ReniecProviderInterface
{
    public function consultar(string $dni): ?ReniecPersonData;
}
