<?php

namespace VientoSur\App\AppBundle\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * AirportRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class AirportRepository extends EntityRepository
{
    public function findAirportsIn($codes)
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb->select('ap')
            ->from('VientoSurAppAppBundle:Airport:Airport', 'ap')
            ->where($qb->expr()->in('ap.code', $codes))
        ;

        return $qb->getQuery()->getResult();
    }
}
