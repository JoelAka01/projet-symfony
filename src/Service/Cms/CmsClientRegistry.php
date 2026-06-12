<?php

declare(strict_types=1);

namespace App\Service\Cms;

use App\Enum\CmsProvider;
use App\Exception\CmsIntegrationException;

final class CmsClientRegistry
{
    /** @var array<string, CmsProviderClientInterface> */
    private array $clients = [];

    /** @param iterable<CmsProviderClientInterface> $clients */
    public function __construct(iterable $clients)
    {
        foreach ($clients as $client) {
            $this->clients[$client->provider()->value] = $client;
        }
    }

    public function for(CmsProvider $provider): CmsProviderClientInterface
    {
        return $this->clients[$provider->value]
            ?? throw new CmsIntegrationException(sprintf('%s CMS publishing is not implemented.', $provider->value));
    }
}
