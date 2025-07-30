<?php

namespace App\Repository;

use App\Entity\Banque;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Banque>
 */
class BanqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Banque::class);
    }

    public function reorganizeOrdres(): void
    {
        $banques = $this->findBy(['actif' => true], ['ordre' => 'ASC']);
        
        $ordre = 1;
        foreach ($banques as $banque) {
            $banque->setOrdre($ordre);
            $ordre++;
        }
        
        $this->getEntityManager()->flush();
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('b.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
