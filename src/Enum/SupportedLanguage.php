<?php

declare(strict_types=1);

namespace App\Enum;

enum SupportedLanguage: string
{
    case FR = 'fr';
    case EN = 'en';
    case ES = 'es';
    case DE = 'de';
    case IT = 'it';
    case PT = 'pt';
    case NL = 'nl';

    public function label(): string
    {
        return match ($this) {
            self::FR => 'Français',
            self::EN => 'English',
            self::ES => 'Español',
            self::DE => 'Deutsch',
            self::IT => 'Italiano',
            self::PT => 'Português',
            self::NL => 'Nederlands',
        };
    }

    public function englishLabel(): string
    {
        return match ($this) {
            self::FR => 'French',
            self::EN => 'English',
            self::ES => 'Spanish',
            self::DE => 'German',
            self::IT => 'Italian',
            self::PT => 'Portuguese',
            self::NL => 'Dutch',
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
        $code = strtolower(trim($code));

        return self::tryFrom($code);
    }

    public static function isSupported(string $code): bool
    {
        return null !== self::fromString($code);
    }
}
