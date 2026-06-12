<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\SubscriptionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Subscription> */
final class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function findActiveForUser(User $user, ?\DateTimeImmutable $at = null): ?Subscription
    {
        $at ??= new \DateTimeImmutable();

        return $this->createQueryBuilder('subscription')
            ->andWhere('subscription.user = :user')
            ->andWhere('subscription.status = :status')
            ->andWhere('subscription.startsAt <= :at')
            ->andWhere('subscription.endsAt > :at')
            ->setParameter('user', $user)
            ->setParameter('status', SubscriptionStatus::ACTIVE)
            ->setParameter('at', $at)
            ->orderBy('subscription.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param list<User> $users
     * @return array<string, Subscription> Map of user ID string to active Subscription
     */
    public function findActiveForUsers(array $users, ?\DateTimeImmutable $at = null): array
    {
        if (empty($users)) {
            return [];
        }

        $at ??= new \DateTimeImmutable();

        /** @var list<Subscription> $subscriptions */
        $subscriptions = $this->createQueryBuilder('subscription')
            ->andWhere('subscription.user IN (:users)')
            ->andWhere('subscription.status = :status')
            ->andWhere('subscription.startsAt <= :at')
            ->andWhere('subscription.endsAt > :at')
            ->setParameter('users', $users)
            ->setParameter('status', SubscriptionStatus::ACTIVE)
            ->setParameter('at', $at)
            ->orderBy('subscription.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($subscriptions as $sub) {
            $user = $sub->getUser();
            if ($user !== null) {
                $userId = $user->getId();
                if (!isset($map[$userId])) {
                    $map[$userId] = $sub;
                }
            }
        }

        return $map;
    }

    /** @return list<Subscription> */
    public function findActiveSubscriptionsForUser(User $user): array
    {
        return $this->findBy(
            ['user' => $user, 'status' => SubscriptionStatus::ACTIVE],
            ['createdAt' => 'DESC'],
        );
    }
}
