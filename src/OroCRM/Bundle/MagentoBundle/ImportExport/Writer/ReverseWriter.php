<?php

namespace OroCRM\Bundle\MagentoBundle\ImportExport\Writer;

use Doctrine\ORM\EntityManager;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

use Akeneo\Bundle\BatchBundle\Item\ItemWriterInterface;

use Oro\Bundle\AddressBundle\Entity\Region as BAPRegion;
use Oro\Bundle\AddressBundle\Entity\Country as BAPCountry;
use Oro\Bundle\AddressBundle\ImportExport\Serializer\Normalizer\AddressNormalizer;
use Oro\Bundle\IntegrationBundle\Form\EventListener\ChannelFormTwoWaySyncSubscriber;

use OroCRM\Bundle\ContactBundle\Entity\ContactAddress;
use OroCRM\Bundle\MagentoBundle\Entity\Region;
use OroCRM\Bundle\MagentoBundle\Entity\Address;
use OroCRM\Bundle\MagentoBundle\Entity\Customer;
use OroCRM\Bundle\MagentoBundle\Converter\RegionConverter;
use OroCRM\Bundle\MagentoBundle\Provider\Transport\SoapTransport;
use OroCRM\Bundle\MagentoBundle\ImportExport\Serializer\CustomerSerializer;
use OroCRM\Bundle\MagentoBundle\Provider\Transport\MagentoTransportInterface;
use OroCRM\Bundle\MagentoBundle\ImportExport\Processor\AbstractReverseProcessor;
use OroCRM\Bundle\MagentoBundle\ImportExport\Strategy\StrategyHelper\AddressImportHelper;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * TODO Should be fixed during CRM-1185
 */
class ReverseWriter implements ItemWriterInterface
{
    const MAGENTO_DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * If at remote side where id no OroBridge installed, will be processed only this fields
     *
     * @var array
     */
    protected $clearMagentoFields = [
        'email',
        'firstname',
        'lastname'
    ];

    /**
     * Customer-Contact relation, key - Customer field, value - Contact field
     *
     * @var array
     */
    protected $customerContactRelation = [
        'name_prefix' => 'name_prefix',
        'first_name'  => 'first_name',
        'middle_name' => 'middle_name',
        'last_name'   => 'last_name',
        'name_suffix' => 'name_suffix',
        'gender'      => 'gender',
        'birthday'    => 'birthday',
        'email'       => 'primary_email.email',
    ];

    /** @var EntityManager */
    protected $em;

    /** @var CustomerSerializer */
    protected $customerSerializer;

    /** @var AddressNormalizer */
    protected $addressNormalizer;

    /** @var SoapTransport */
    protected $transport;

    /** @var PropertyAccessor */
    protected $accessor;

    /** @var AddressImportHelper */
    protected $addressImportHelper;

    /** @var RegionConverter */
    protected $regionConverter;

    /**
     * @param EntityManager       $em
     * @param CustomerSerializer  $customerSerializer
     * @param AddressNormalizer   $addressNormalizer
     * @param SoapTransport       $transport
     * @param AddressImportHelper $addressImportHelper
     * @param RegionConverter     $regionConverter
     */
    public function __construct(
        EntityManager $em,
        CustomerSerializer $customerSerializer,
        AddressNormalizer $addressNormalizer,
        SoapTransport $transport,
        AddressImportHelper $addressImportHelper,
        RegionConverter $regionConverter
    ) {
        $this->em                  = $em;
        $this->customerSerializer  = $customerSerializer;
        $this->addressNormalizer   = $addressNormalizer;
        $this->transport           = $transport;
        $this->accessor            = PropertyAccess::createPropertyAccessor();
        $this->addressImportHelper = $addressImportHelper;
        $this->regionConverter     = $regionConverter;
    }

    /**
     * {@inheritDoc}
     */
    public function write(array $items)
    {
        foreach ($items as $item) {
            if (!empty($item->object)) {
                try {
                    /** @var Customer $customer */
                    $customer = $item->entity;
                    $channel  = $customer->getChannel();
                    if (!empty($item->object['email'])) {
                        $item->object['email'] = $this->emailParser($item->object['email']);
                    }
                    $this->transport->init($channel->getTransport());
                    $localUpdatedData = $this->customerSerializer->normalize($item->entity, null, $item->object);

                    // REMOTE WINS
                    if ($channel->getSyncPriority() === ChannelFormTwoWaySyncSubscriber::REMOTE_WINS) {
                        $remoteChanges = $this->getCustomerRemoteChangeSet(
                            $item,
                            array_keys($localUpdatedData)
                        );
                        $this->setChangedData($customer, $item->object);
                        $this->setChangedData($customer, $remoteChanges);
                        $this->em->persist($customer);
                        $customerForMagento = $this->customerSerializer->normalize($customer);
                        $this->updateRemoteData($customer->getOriginId(), $customerForMagento);
                    } else {
                        // local wins
                        $this->updateRemoteData($customer->getOriginId(), $localUpdatedData);
                        $this->setChangedData($customer, $item->object);
                        $this->em->persist($customer);
                    }

                    // process addresses
                    if (isset($item->object['addresses'])) {
                        $this->processAddresses(
                            $item->object['addresses'],
                            $channel->getSyncPriority(),
                            $customer
                        );
                    }
                } catch (\Exception $e) {
                    //process another entity even in case if exception thrown
                    continue;
                }
            }
        }
        $this->em->flush();
    }

    /**
     * Process address write  to remote instance and to DB
     *
     * @param array    $addresses
     * @param string   $syncPriority
     * @param Customer $customer
     *
     * @throws \LogicException
     */
    protected function processAddresses($addresses, $syncPriority, Customer $customer)
    {
        foreach ($addresses as $address) {
            if (empty($address['status']) || empty($address['entity'])) {
                throw new \LogicException('Unable to process entity modification');
            }

            /** @var ContactAddress|Address $addressEntity */
            $addressEntity = $address['entity'];
            $status        = $address['status'];
            $defaultData   = [
                'firstname' => $customer->getFirstName(),
                'lastname'  => $customer->getLastName()
            ];

            if ($status === AbstractReverseProcessor::UPDATE_ENTITY) {
                $localChanges = $address['object'];

                if ($syncPriority === ChannelFormTwoWaySyncSubscriber::REMOTE_WINS) {
                    $answer = (array)$this->transport->call(
                        SoapTransport::ACTION_CUSTOMER_ADDRESS_INFO,
                        ['addressId' => $addressEntity->getOriginId()]
                    );
                    $remoteData = $this->customerSerializer->compareAddresses($answer, $addressEntity);
                    $this->setLocalDataChanges($addressEntity, $localChanges);
                    $this->setRemoteDataChanges($addressEntity, $remoteData);
                } else {
                    $this->setChangedData($addressEntity, $localChanges);
                }

                $dataForSend = array_merge(
                    $this->customerSerializer->convertToMagentoAddress($addressEntity, $defaultData),
                    $this->regionConverter->toMagentoData($addressEntity)
                );
                $requestData = ['addressId' => $addressEntity->getOriginId(), 'addressData' => $dataForSend];
                try {
                    $this->transport->call(SoapTransport::ACTION_CUSTOMER_ADDRESS_UPDATE, $requestData);
                    $this->em->persist($addressEntity);
                } catch (\Exception $e) {
                }
            } elseif ($status === AbstractReverseProcessor::NEW_ENTITY) {
                try {
                    $addressData = array_merge(
                        ['telephone' => 'no phone'],
                        $this->customerSerializer->convertToMagentoAddress($addressEntity, $defaultData),
                        $this->regionConverter->toMagentoData($addressEntity)
                    );
                    $requestData = ['customerId' => $address['magentoId'], 'addressData' => $addressData];
                    $result      = $this->transport->call(
                        SoapTransport::ACTION_CUSTOMER_ADDRESS_CREATE,
                        $requestData
                    );

                    if ($result) {
                        $newAddress = $this->customerSerializer
                            ->convertMageAddressToAddress($addressData, $addressEntity, $result);
                        $newAddress->setOwner($customer);
                        $customer->addAddress($newAddress);
                        $this->em->persist($customer);
                    }
                } catch (\Exception $e) {
                }
            } elseif ($status === AbstractReverseProcessor::DELETE_ENTITY) {
                try {
                    $shouldBeRemoved = $this->transport->call(
                        SoapTransport::ACTION_CUSTOMER_ADDRESS_DELETE,
                        ['addressId' => $addressEntity->getOriginId()]
                    );
                } catch (\Exception $e) {
                    // remove from local customer if it's already removed on remote side
                    $errorCode       = $this->transport->getErrorCode($e);
                    $shouldBeRemoved = $errorCode === MagentoTransportInterface::TRANSPORT_ERROR_ADDRESS_DOES_NOT_EXIST;
                }

                if ($shouldBeRemoved) {
                    $this->em->remove($address['entity']);
                }
            }
        }
    }

    /**
     * Push data to remote instance
     *
     * @param int   $customerId
     * @param array $customerData
     */
    protected function updateRemoteData($customerId, $customerData)
    {
        foreach ($customerData as $fieldName => $value) {
            if ($value instanceof \DateTime) {
                /** @var $value \DateTime */
                $customerData[$fieldName] = $value->format(self::MAGENTO_DATETIME_FORMAT);
            }
        }
        $requestData = ['customerId' => $customerId, 'customerData' => $customerData];
        $this->transport->call(SoapTransport::ACTION_CUSTOMER_UPDATE, $requestData);
    }

    /**
     * Get changes from magento side
     *
     * @param \stdClass $item
     * @param array     $fieldsList
     *
     * @return array
     */
    protected function getCustomerRemoteChangeSet($item, $fieldsList)
    {
        $remoteData = $this->transport->call(
            SoapTransport::ACTION_CUSTOMER_INFO,
            [
            'customerId' => $item->entity->getOriginId(),
            'attributes' => $fieldsList
            ]
        );

        unset($remoteData->customer_id);

        /** cut data */
        $this->fixDataIfExtensionNotInstalled($remoteData);
        $customerLocalData = $this->customerSerializer->getCurrentCustomerValues(
            $item->entity,
            $fieldsList
        );
        foreach ($remoteData as $fieldName => $value) {
            if (isset($customerLocalData[$fieldName]) && $customerLocalData[$fieldName] === $value) {
                unset ($remoteData->{$fieldName});
            }
        }

        return (array)$remoteData;
    }

    /**
     * Set changed data to customer
     *
     * @param object $entity
     * @param array  $changedData
     */
    protected function setChangedData($entity, array $changedData)
    {
        foreach ($changedData as $fieldName => $value) {
            if ($fieldName !== 'addresses') {
                $this->accessor->setValue($entity, $fieldName, $value);
            }
        }
    }

    /**
     * Set changed data to customer
     *
     * @param object $entity
     * @param array  $changedData
     */
    protected function setLocalDataChanges($entity, array $changedData)
    {
        foreach ($changedData as $fieldName => $value) {
            if ($fieldName !== 'addresses' && $fieldName !== 'region') {
                $this->accessor->setValue($entity, $fieldName, $value);
            }
        }
    }

    /**
     * @param Address $entity
     * @param array   $changedData
     */
    protected function setRemoteDataChanges($entity, array $changedData)
    {
        foreach ($changedData as $fieldName => $value) {
            if ($fieldName !== 'addresses') {
                if ($fieldName === 'region') {
                    try {
                        $mageRegionId = $this->accessor->getValue($value, 'code');
                        $magentoRegion = $this->addressImportHelper->findRegionByRegionId($mageRegionId);

                        if ($magentoRegion instanceof Region) {
                            $this->accessor->setValue(
                                $entity,
                                $fieldName,
                                $this->getChangedRegion($entity, $magentoRegion)
                            );
                        }
                    } catch (\Exception $e) {
                        $this->accessor->setValue($entity, $fieldName, null);
                        $this->accessor->setValue($entity, 'contact_address.region', null);
                    }
                } elseif ($fieldName === 'country') {
                    if ($value instanceof BAPCountry) {
                        if (!$value->getIso3Code()) {
                            $country = $this->em->getRepository('OroAddressBundle:Country')
                                ->findOneBy(['iso2Code'=>$value->getIso2Code()]);
                        } else {
                            $country = $value;
                        }

                        $this->accessor->setValue(
                            $entity,
                            $fieldName,
                            $this->getChangedCountry($entity, $country)
                        );
                    }
                } else {
                    try {
                        $this->accessor->setValue($entity, $fieldName, $value);
                    } catch (\Exception $e) {
                    }
                }
            }
        }
    }

    /**
     * @param Address $entity
     * @param BAPCountry $magentoCountry
     *
     * @return mixed
     */
    protected function getChangedCountry($entity, $magentoCountry)
    {
        $magentoCountryCode  = $this->accessor->getValue($magentoCountry, 'iso2_code');
        $customerCountryCode = $this->accessor->getValue($entity, 'country.iso2_code');
        $contactCountryCode  = $this->accessor->getValue($entity, 'contact_address.country.iso2_code');

        if ($magentoCountryCode !== $customerCountryCode) {
            $this->accessor->setValue($entity, 'contact_address.country', $magentoCountry);
            return $magentoCountry;
        }

        if ($contactCountryCode !== $customerCountryCode) {
            $this->accessor->setValue(
                $entity,
                'country',
                $this->accessor->getValue($entity, 'contact_address.country')
            );
        }

        return $this->accessor->getValue($entity, 'contact_address.country');
    }

    /**
     * @param Address $entity
     * @param Region $magentoRegion
     *
     * @return mixed|BAPRegion
     */
    protected function getChangedRegion($entity, $magentoRegion)
    {
        $magentoRegionCode  = $this->accessor->getValue($magentoRegion, 'combined_code');
        $customerRegionCode = $this->accessor->getValue($entity, 'region.combined_code');
        $contactRegionCode  = $this->accessor->getValue($entity, 'contact_address.region.combined_code');

        if ($magentoRegionCode !== $customerRegionCode) {
            $bapRegion = $this->em->getRepository('OroAddressBundle:Region')
                ->findOneBy(['combinedCode' => $magentoRegionCode]);
            $this->accessor->setValue($entity, 'contact_address.region', $bapRegion);

            return $bapRegion;
        }

        if ($contactRegionCode !== $customerRegionCode) {
            $this->accessor->setValue($entity, 'region', $this->accessor->getValue($entity, 'contact_address.region'));
        }

        return $this->accessor->getValue($entity, 'contact_address.region');
    }

    /**
     * Convert email to sting
     *
     * @param mixed $email
     *
     * @return string|null
     */
    protected function emailParser($email)
    {
        if (is_object($email)) {
            try {
                return (string)$email;
            } catch (\Exception $e) {
                $email = null;
            }
        }

        return $email;
    }

    /**
     * Check if magento extension not installed and fix data set
     * In the magento version up to 1.8.0.0 we can send only: email, firstname, lastname.
     *
     * @param \stdClass $remoteData
     */
    protected function fixDataIfExtensionNotInstalled(\stdClass $remoteData)
    {
        if (!$this->transport->isExtensionInstalled()) {
            foreach ($remoteData as $key => $value) {
                if (!in_array($key, $this->clearMagentoFields)) {
                    unset($remoteData->$key);
                }
            }
        }
    }
}
