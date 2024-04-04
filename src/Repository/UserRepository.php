<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Returns a list of User associated with the Customer.
     *
     * @return User[]
     */
    public function findAllUserWithPagination(int $customerId, int $page, int $limit): array
    {
        /** @var User[] $users * */
        $users = $this->createQueryBuilder('u')
            ->innerJoin('u.customers', 'c')
            ->where('c.id = :customerId')
            ->setParameter('customerId', $customerId)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $users;
    }
}
