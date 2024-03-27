<?php

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 *
 * @method Customer|null find($id, $lockMode = null, $lockVersion = null)
 * @method Customer|null findOneBy(array $criteria, array $orderBy = null)
 * @method Customer[]    findAll()
 * @method Customer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    /**
     * Returns a list of customer.
     *
     * @return Customer[]
     */
    public function findAllCustomersWithPagination(int $page, int $limit): array
    {
        /** @var Customer[] $customers * */
        $customers = $this->createQueryBuilder('c')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $customers;
    }

    /**
     * Returns a list of User associated with the Customer.
     *
     * @param array<int|Customer> $customerList
     *
     * @return User[]
     */
    public function findUsersByCustomer(array $customerList): array
    {
        /** @var User[] $users * */
        $users = $this->createQueryBuilder('c')
            ->leftJoin('c.users', 'u')
            ->addSelect('u')
            ->where('c in (:customerList)')
            ->setParameter('customerList', $customerList)
            ->getQuery()
            ->getResult();

        return $users;
    }
}
