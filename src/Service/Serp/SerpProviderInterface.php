<?php

declare(strict_types=1);

namespace App\Service\Serp;

use App\Dto\SerpResultDto;

interface SerpProviderInterface
{
    public function search(string $query, string $country, string $language): SerpResultDto;

    /** @return list<string> */
    public function suggest(string $query, string $country, string $language): array;
}
