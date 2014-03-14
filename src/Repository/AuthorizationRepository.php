<?php

namespace MyCLabs\ACL\Repository;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityRepository;
use MyCLabs\ACL\Model\Authorization;
use MyCLabs\ACL\Model\ClassFieldResource;
use MyCLabs\ACL\Model\ClassResource;
use MyCLabs\ACL\Model\EntityFieldResource;
use MyCLabs\ACL\Model\EntityResource;
use MyCLabs\ACL\Model\ResourceInterface;

/**
 * Authorizations repository.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 */
class AuthorizationRepository extends EntityRepository
{
    /**
     * Insert authorizations directly in database without using the entity manager.
     *
     * This is much more optimized than using the entity manager.
     * This methods inserts in batch of 1000 inserts, each batch being in a transaction. It is to
     * avoid locking the authorizations table for too long, which could impact other web requests.
     *
     * @param Authorization[] $authorizations
     * @throws \RuntimeException Parent authorizations in the array must appear before their children.
     */
    public function insertBulk(array $authorizations)
    {
        $connection = $this->getEntityManager()->getConnection();
        $connection->beginTransaction();

        $tableName = $this->getClassMetadata()->getTableName();

        $i = 0;

        foreach ($authorizations as $authorization) {
            // Check parent authorization is persisted
            $parent = $authorization->getParentAuthorization();
            if ($parent !== null && $parent->getId() === null) {
                throw new \RuntimeException(
                    'An authorization has a parent with no ID. Parent authorizations should appear before their'
                    . ' children in the authorizations array so that they can be persisted first (to have an ID)'
                );
            }

            $data = [
                'role_id'                => $authorization->getRole()->getId(),
                'securityIdentity_id'    => $authorization->getSecurityIdentity()->getId(),
                'parentAuthorization_id' => $parent ? $parent->getId() : null,
                'entity_class'           => $authorization->getEntityClass(),
                'entity_id'              => $authorization->getEntityId(),
                'entity_field'           => $authorization->getEntityField(),
            ];

            foreach ($authorization->getActions()->toArray() as $action => $value) {
                $data['actions_' . $action] = $value;
            }

            $connection->insert($tableName, $data);

            // Set authorization ID (used if parent of other authorizations to be inserted)
            $authorization->setId($connection->lastInsertId());

            // Commit every 1000 inserts to avoid locking the table too long
            if (($i % 1000) === 0) {
                $connection->commit();
                $connection->beginTransaction();
            }

            $i++;
        }

        $connection->commit();
    }

    /**
     * Returns authorization for the given resource that are not cascaded authorizations,
     * i.e. they have no parent authorization.
     *
     * @param ResourceInterface $resource
     * @return Authorization[]
     */
    public function findNonCascadedAuthorizationsForResource(ResourceInterface $resource)
    {
        $qb = $this->createQueryBuilder('a');

        // Root authorizations means no parent
        $qb->where('a.parentAuthorization IS NULL');

        if ($resource instanceof EntityResource) {
            $qb->andWhere('a.entityClass = :entityClass');
            $qb->andWhere('a.entityId = :entityId');
            $qb->andWhere('a.entityField IS NULL');
            $qb->setParameter('entityClass', ClassUtils::getClass($resource));
            $qb->setParameter('entityId', $resource->getId());
        }
        if ($resource instanceof ClassResource) {
            $qb->andWhere('a.entityClass = :entityClass');
            $qb->andWhere('a.entityId IS NULL');
            $qb->andWhere('a.entityField IS NULL');
            $qb->setParameter('entityClass', $resource->getClass());
        }
        if ($resource instanceof EntityFieldResource) {
            $qb->andWhere('a.entityClass = :entityClass');
            $qb->andWhere('a.entityId = :entityId');
            $qb->andWhere('a.entityField = :entityField');
            $qb->setParameter('entityClass', ClassUtils::getClass($resource));
            $qb->setParameter('entityId', $resource->getEntity()->getId());
            $qb->setParameter('entityField', $resource->getField());
        }
        if ($resource instanceof ClassFieldResource) {
            $qb->andWhere('a.entityClass = :entityClass');
            $qb->andWhere('a.entityId IS NULL');
            $qb->andWhere('a.entityField = :entityField');
            $qb->setParameter('entityClass', $resource->getClass());
            $qb->setParameter('entityField', $resource->getField());
        }

        return $qb->getQuery()->getResult();
    }
}
