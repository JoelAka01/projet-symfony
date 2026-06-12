<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Payment;
use App\Enum\PaymentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Payment> */
final class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /** @return list<Payment> */
    public function findForAdmin(?PaymentStatus $status = null): array
    {
        $queryBuilder = $this->createQueryBuilder('payment')
            ->addSelect('user', 'subscription')
            ->leftJoin('payment.user', 'user')
            ->leftJoin('payment.subscription', 'subscription')
            ->orderBy('payment.createdAt', 'DESC');

        if (null !== $status) {
            $queryBuilder
                ->andWhere('payment.status = :status')
                ->setParameter('status', $status);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function countPaid(): int
    {
        return $this->count(['status' => PaymentStatus::PAID]);
    }

    public function sumPaidAmountCents(): int
    {
        return (int) $this->createQueryBuilder('payment')
            ->select('COALESCE(SUM(payment.amountCents), 0)')
            ->andWhere('payment.status = :status')
            ->setParameter('status', PaymentStatus::PAID)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
