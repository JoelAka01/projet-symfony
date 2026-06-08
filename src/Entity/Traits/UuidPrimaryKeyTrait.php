<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

trait UuidPrimaryKeyTrait
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    public function getId(): string
    {
        return $this->id;
    }

    protected function initializeUuid(): void
    {
        $this->id = self::generateUuid();
    }

    private static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
