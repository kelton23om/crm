<?php

namespace OroCRM\Bundle\MagentoBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;

use BeSimple\SoapBundle\ServiceDefinition\Annotation as Soap;

use Oro\Bundle\DataAuditBundle\Metadata\Annotation as Oro;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\Config;

use OroCRM\Bundle\MagentoBundle\Model\ExtendAddress;

use OroCRM\Bundle\ContactBundle\Entity\ContactAddress;

/**
 * @ORM\Table("orocrm_magento_customer_addr")
 * @ORM\HasLifecycleCallbacks()
 * @Config()
 * @ORM\Entity
 * @Oro\Loggable
 */
class Address extends ExtendAddress
{
    use OriginTrait;

    /*
     * FIELDS are duplicated to enable dataaudit only for customer address fields
     */
    /**
     * @var string
     *
     * @ORM\Column(name="label", type="string", length=255, nullable=true)
     * @Soap\ComplexType("string", nillable=true)
     * @Oro\Versioned
     */
    protected $label;

    /**
     * @var string
     *
     * @ORM\Column(name="street", type="string", length=500, nullable=true)
     * @Soap\ComplexType("string", nillable=true)
     * @Oro\Versioned
     */
    protected $street;

    /**
     * @var string
     *
     * @ORM\Column(name="street2", type="string", length=500, nullable=true)
     * @Soap\ComplexType("string", nillable=true)
     * @Oro\Versioned
     */
    protected $street2;

    /**
     * @var string
     *
     * @ORM\Column(name="city", type="string", length=255, nullable=true)
     * @Soap\ComplexType("string", nillable=true)
     * @Oro\Versioned
     */
    protected $city;

    /**
     * @var string
     *
     * @ORM\Column(name="postal_code", type="string", length=20, nullable=true)
     * @Soap\ComplexType("string", nillable=true)
     * @Oro\Versioned
     */
    protected $postalCode;

    /**
     * @var string
     *
     * @ORM\Column(name="region_text", type="string", length=255, nullable=true)
     * @Soap\ComplexType("string", nillable=true)
     * @Oro\Versioned
     */
    protected $regionText;

    /**
     * @var string
     *
     * @ORM\Column(name="name_prefix", type="string", length=255, nullable=true)
     * @Soap\ComplexType("string", nillable=true)
     * @Oro\Versioned
     */
    protected $namePrefix;

    /**
     * @var string
     *
     * @ORM\Column(name="first_name", type="string", length=255, nullable=true)
     * @Soap\ComplexType("string", nillable=true)
     * @Oro\Versioned
     */
    protected $firstName;

    /**
     * @var string
     *
     * @ORM\Column(name="middle_name", type="string", length=255, nullable=true)
     * @Soap\ComplexType("string", nillable=true)
     * @Oro\Versioned
     */
    protected $middleName;

    /**
     * @var string
     *
     * @ORM\Column(name="last_name", type="string", length=255, nullable=true)
     * @Soap\ComplexType("string", nillable=true)
     * @Oro\Versioned
     */
    protected $lastName;

    /**
     * @var string
     *
     * @ORM\Column(name="name_suffix", type="string", length=255, nullable=true)
     * @Soap\ComplexType("string", nillable=true)
     * @Oro\Versioned
     */
    protected $nameSuffix;

    /**
     * @ORM\ManyToOne(targetEntity="Customer", inversedBy="addresses",cascade={"persist"})
     * @ORM\JoinColumn(name="owner_id", referencedColumnName="id", onDelete="CASCADE")
     */
    protected $owner;

    /**
     * @var Collection
     *
     * @ORM\ManyToMany(targetEntity="Oro\Bundle\AddressBundle\Entity\AddressType",cascade={"persist"})
     * @ORM\JoinTable(
     *     name="orocrm_magento_cust_addr_type",
     *     joinColumns={@ORM\JoinColumn(name="customer_address_id", referencedColumnName="id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="type_name", referencedColumnName="name")}
     * )
     **/
    protected $types;

    /**
     * @ORM\OneToOne(targetEntity="OroCRM\Bundle\ContactBundle\Entity\ContactAddress",cascade={"persist"})
     * @ORM\JoinColumn(name="related_contact_address_id", referencedColumnName="id", onDelete="SET NULL")
     * @var ContactAddress
     */
    protected $contactAddress;

    /**
     * Set contact as owner.
     *
     * @param Customer $owner
     */
    public function setOwner(Customer $owner = null)
    {
        $this->owner = $owner;
    }

    /**
     * Get owner customer.
     *
     * @return Customer
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @param ContactAddress $contactAddress
     */
    public function setContactAddress(ContactAddress $contactAddress = null)
    {
        $this->contactAddress = $contactAddress;
    }

    /**
     * @return ContactAddress
     */
    public function getContactAddress()
    {
        return $this->contactAddress;
    }

    /**
     * Set address created date/time
     *
     * @param \DateTime $created
     */
    public function setCreated($created)
    {
        $this->created = $created;
    }

    /**
     * Get address created date/time
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set address updated date/time
     *
     * @param \DateTime $updated
     */
    public function setUpdated($updated)
    {
        $this->updated = $updated;
    }

    /**
     * Get address last update date/time
     *
     * @return \DateTime
     */
    public function getUpdated()
    {
        return $this->updated;
    }
}
