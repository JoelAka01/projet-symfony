<?php

declare(strict_types=1);

namespace App\Enum;

enum CmsProvider: string
{
    case WORDPRESS = 'WORDPRESS';
    case SHOPIFY = 'SHOPIFY';
    case WEBFLOW = 'WEBFLOW';
    case WIX = 'WIX';
    case CUSTOM_WEBHOOK = 'CUSTOM_WEBHOOK';
}
