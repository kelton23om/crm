<?php

namespace OroCRM\Bundle\MagentoBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

use Oro\Bundle\AddressBundle\Entity\AbstractTypedAddress;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\Config;

/**
 * @ORM\Table("orocrm_magento_order_address")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity
 */
class OrderAddress extends AbstractTypedAddress
{
    /**
     * @var ArrayCollection
     *
     * @ORM\ManyToMany(targetEntity="Oro\Bundle\AddressBundle\Entity\AddressType",cascade={"persist"})
     * @ORM\JoinTable(
     *     name="orocrm_magento_order_address_to_address_type",
     *     joinColumns={@ORM\JoinColumn(name="order_address_id", referencedColumnName="id")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="type_name", referencedColumnName="name")}
     * )
     **/
    protected $types;

    /**
     * @var Order
     *
     * @ORM\ManyToOne(targetEntity="Order", inversedBy="addresses",cascade={"persist"})
     * @ORM\JoinColumn(name="owner_id", referencedColumnName="id", onDelete="CASCADE")
     */
    protected $owner;

    /**
     * @var string
     *
     * @ORM\Column(name="fax", type="string", length=255, nullable=true)
     */
    protected $fax;

    /**
     * @var string
     *
     * @ORM\Column(name="phone", type="string", length=255, nullable=true)
     */
    protected $phone;

    /**
     * @param Order $owner
     *
     * @return $this
     */
    public function setOwner(Order $owner)
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return Order
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @param string $fax
     *
     * @return $this
     */
    public function setFax($fax)
    {
        $this->fax = $fax;

        return $this;
    }

    /**
     * @return string
     */
    public function getFax()
    {
        return $this->fax;
    }

    /**
     * @param string $phone
     *
     * @return $this
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }
}
