<?php

declare(strict_types=1);

namespace App\Enum;

enum SitePageType: string
{
    case HOME = 'HOME';
    case SERVICE = 'SERVICE';
    case PRODUCT = 'PRODUCT';
    case CATEGORY = 'CATEGORY';
    case BLOG = 'BLOG';
    case CONTACT = 'CONTACT';
    case QUOTE = 'QUOTE';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::HOME => 'Home',
            self::SERVICE => 'Service',
            self::PRODUCT => 'Product',
            self::CATEGORY => 'Category',
            self::BLOG => 'Blog',
            self::CONTACT => 'Contact',
            self::QUOTE => 'Quote',
            self::OTHER => 'Other',
        };
    }
}
