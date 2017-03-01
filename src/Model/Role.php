<?php

namespace MyCLabs\ACL\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use MyCLabs\ACL\ACL;

/**
 * Role.
 *
 * @ORM\Entity(repositoryClass="MyCLabs\ACL\Repository\RoleRepository")
 * @ORM\InheritanceType("JOINED")
 * @ORM\DiscriminatorColumn(name="type", type="string")
 * @ORM\Table(name="ACL_Role")
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 */
abstract class Role
{
    /**
     * @var string
     * @ORM\Id 
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="guid")
     */
    protected $id;

    /**
     * @var SecurityIdentityInterface
     * @ORM\ManyToOne(targetEntity="SecurityIdentityInterface", inversedBy="roles")
     */
    protected $securityIdentity;

    /**
     * @var Authorization[]|Collection
     * @ORM\OneToMany(targetEntity="Authorization", mappedBy="role", fetch="EXTRA_LAZY")
     */
    protected $authorizations;

    public function __construct(SecurityIdentityInterface $identity)
    {
        $this->authorizations = new ArrayCollection();
        $this->securityIdentity = $identity;
    }

    /**
     * @param ACL $acl
     */
    abstract public function createAuthorizations(ACL $acl);

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return SecurityIdentityInterface
     */
    public function getSecurityIdentity()
    {
        return $this->securityIdentity;
    }
}
