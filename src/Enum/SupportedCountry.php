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
    case ES = 'ES';
    case DE = 'DE';
    case IT = 'IT';
    case NL = 'NL';
    case PT = 'PT';
    case BR = 'BR';
    case MX = 'MX';
    case AT = 'AT';
    case LU = 'LU';

    public function label(): string
    {
        return match ($this) {
            self::FR => 'France',
            self::CA => 'Canada',
            self::BE => 'Belgique',
            self::CH => 'Suisse',
            self::US => 'United States',
            self::GB => 'United Kingdom',
            self::ES => 'España',
            self::DE => 'Deutschland',
            self::IT => 'Italia',
            self::NL => 'Nederland',
            self::PT => 'Portugal',
            self::BR => 'Brasil',
            self::MX => 'México',
            self::AT => 'Österreich',
            self::LU => 'Luxembourg',
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
            self::FR, self::BE, self::LU => SupportedLanguage::FR,
            self::US, self::GB, self::CA => SupportedLanguage::EN,
            self::ES, self::MX => SupportedLanguage::ES,
            self::DE, self::AT, self::CH => SupportedLanguage::DE,
            self::IT => SupportedLanguage::IT,
            self::PT, self::BR => SupportedLanguage::PT,
            self::NL => SupportedLanguage::NL,
        };
    }
}
