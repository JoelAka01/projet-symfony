<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<User> */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /** @return list<User> */
    public function findForAdmin(?string $search = null, ?UserRole $role = null): array
    {
        $queryBuilder = $this->createQueryBuilder('user')
            ->orderBy('user.createdAt', 'DESC');

        $search = trim((string) $search);
        if ('' !== $search) {
            $queryBuilder
                ->andWhere('LOWER(user.email) LIKE :search OR LOWER(user.firstName) LIKE :search OR LOWER(user.lastName) LIKE :search')
                ->setParameter('search', '%' . strtolower($search) . '%');
        }

        if (null !== $role) {
            $queryBuilder
                ->andWhere('user.role = :role')
                ->setParameter('role', $role);
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
