<?php

declare(strict_types=1);

namespace App\Enum;

enum SupportedCountry: string
{
    case FR = 'FR';
    case CA = 'CA';
    case BE = 'BE';
    case CH = 'CH';
    case US = 'US';
    case GB = 'GB';
    case CI = 'CI';

    public function label(): string
    {
        return match ($this) {
            self::FR => 'France',
            self::CA => 'Canada',
            self::BE => 'Belgique',
            self::CH => 'Suisse',
            self::US => 'United States',
            self::GB => 'United Kingdom',
            self::CI => 'Côte d\'Ivoire',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case->value;
        }

        return $choices;
    }

    public static function fromString(string $code): ?self
    {
        $code = strtoupper(trim($code));

        return self::tryFrom($code);
    }

    public static function isSupported(string $code): bool
    {
        return null !== self::fromString($code);
    }

    /**
     * Returns the most likely default language for this country.
     */
    public function defaultLanguage(): SupportedLanguage
    {
        return match ($this) {
            self::FR, self::BE, self::CI => SupportedLanguage::FR,
            self::US, self::GB, self::CA => SupportedLanguage::EN,
            self::CH => SupportedLanguage::DE,
        };
    }
}
