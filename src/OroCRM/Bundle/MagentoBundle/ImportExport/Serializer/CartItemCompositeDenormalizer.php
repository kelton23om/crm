<?php

namespace OroCRM\Bundle\MagentoBundle\ImportExport\Serializer;

use Doctrine\ORM\EntityManager;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

use OroCRM\Bundle\MagentoBundle\Entity\CartItem;
use OroCRM\Bundle\MagentoBundle\ImportExport\Converter\CartItemDataConverter;
use OroCRM\Bundle\MagentoBundle\Provider\MagentoConnectorInterface;

class CartItemCompositeDenormalizer extends AbstractNormalizer implements DenormalizerInterface
{
    /** @var CartItemDataConverter */
    protected $itemConverter;

    public function __construct(EntityManager $em, CartItemDataConverter $itemConverter)
    {
        parent::__construct($em);
        $this->itemConverter = $itemConverter;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return $type == MagentoConnectorInterface::CART_ITEM_TYPE;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $cartItem = new CartItem();

        if (!is_array($data)) {
            return $cartItem;
        }

        $data = $this->itemConverter->convertToImportFormat($data);
        $data = $this->denormalizeCreatedUpdated($data, $format, $context);
        $this->fillResultObject($cartItem, $data);

        return $cartItem;
    }
}
