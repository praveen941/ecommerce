<?php

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\CustomerBundle\Entity;

use Sonata\Component\Customer\AddressInterface;
use Sonata\Component\Customer\AddressManagerInterface;
use Sonata\CoreBundle\Model\BaseEntityManager;
use Sonata\DatagridBundle\Pager\Doctrine\Pager;
use Sonata\DatagridBundle\ProxyQuery\Doctrine\ProxyQuery;

class AddressManager extends BaseEntityManager implements AddressManagerInterface
{
    /**
     * {@inheritdoc}
     */
    public function setCurrent(AddressInterface $address)
    {
        foreach ($address->getCustomer()->getAddressesByType($address->getType()) as $custAddress) {
            if ($custAddress->getCurrent()) {
                $custAddress->setCurrent(false);
                $this->save($custAddress);
                break;
            }
        }

        $address->setCurrent(true);
        $this->save($address);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($address, $andFlush = true)
    {
        if ($address->getCurrent()) {
            $custAddresses = $address->getCustomer()->getAddressesByType(AddressInterface::TYPE_DELIVERY);

            if (count($custAddresses) > 1) {
                foreach ($custAddresses as $currentAddress) {
                    if ($currentAddress->getId() !== $address->getId()) {
                        $currentAddress->setCurrent(true);
                        $this->save($currentAddress);
                        break;
                    }
                }
            }
        }

        parent::delete($address, $andFlush);
    }

    /**
     * {@inheritdoc}
     */
    public function getPager(array $criteria, $page, $limit = 10, array $sort = array())
    {
        $query = $this->getRepository()
            ->createQueryBuilder('a')
            ->select('a');

        $fields = $this->getEntityManager()->getClassMetadata($this->class)->getFieldNames();
        foreach ($sort as $field => $direction) {
            if (!in_array($field, $fields)) {
                throw new \RuntimeException(sprintf("Invalid sort field '%s' in '%s' class", $field, $this->class));
            }
        }
        if (count($sort) == 0) {
            $sort = array('name' => 'ASC');
        }
        foreach ($sort as $field => $direction) {
            $query->orderBy(sprintf('a.%s', $field), strtoupper($direction));
        }

        $parameters = array();

        if (isset($criteria['customer'])) {
            $query->innerJoin('a.customer', 'c', 'WITH', 'c.id = :customer');
            $parameters['customer'] = $criteria['customer'];
        }

        $query->setParameters($parameters);

        $pager = new Pager();
        $pager->setMaxPerPage($limit);
        $pager->setQuery(new ProxyQuery($query));
        $pager->setPage($page);
        $pager->init();

        return $pager;
    }
}
