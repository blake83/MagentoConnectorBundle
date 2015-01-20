<?php

namespace Pim\Bundle\MagentoConnectorBundle\Normalizer;

use Pim\Bundle\CatalogBundle\Entity\AttributeOption;
use Pim\Bundle\CatalogBundle\Model\AbstractAttribute;
use Pim\Bundle\MagentoConnectorBundle\Helper\AttributeMappingHelper;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\Dictionary\AttributeLabelDictionary;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Attribute normalizer
 *
 * @author    Willy Mesnage <willy.mesnage@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class AttributeNormalizer implements NormalizerInterface, SerializerAwareInterface
{
    /** @var AttributeMappingHelper */
    protected $mappingHelper;

    /** @var NormalizerInterface */
    protected $normalizer;

    /**
     * @param AttributeMappingHelper $mappingHelper
     */
    public function __construct(AttributeMappingHelper $mappingHelper)
    {
        $this->mappingHelper = $mappingHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($attribute, $format = null, array $context = [])
    {
        $pimAttributeType = $attribute->getAttributeType();
        $pimBackendType   = $attribute->getBackendType();
        $attributeType    = $this->mappingHelper->getMagentoAttributeType($pimAttributeType);
        $backendType      = $this->mappingHelper->getMagentoBackendType($pimBackendType);

        $normalized = [
            AttributeLabelDictionary::ID_HEADER            => $attribute->getCode(),
            AttributeLabelDictionary::DEFAULT_VALUE_HEADER => $attribute->getDefaultValue(),
            AttributeLabelDictionary::INPUT_HEADER         => $attributeType,
            AttributeLabelDictionary::BACKEND_TYPE_HEADER  => $backendType,
            AttributeLabelDictionary::LABEL_HEADER         =>
                $attribute->getTranslation($context['defaultLocale'])->getLabel(),
            // Attributes can't be Global as AKeneo doesn't have global scope (see doc)
            AttributeLabelDictionary::GLOBAL_HEADER        => false,
            AttributeLabelDictionary::REQUIRED_HEADER      => $attribute->isRequired(),
            AttributeLabelDictionary::VISIBLE_HEADER       => $context['visibility'],
            AttributeLabelDictionary::IS_UNIQUE_HEADER     => $attribute->isUnique()
        ];

        if ('pim_catalog_simpleselect' === $pimAttributeType ||
            'pim_catalog_multiselect' === $pimAttributeType
        ) {
            foreach ($attribute->getOptions() as $option) {
                $normalized['option']['value'][$option->getCode()] = $this->getValidOptionValues($option, $context);
                $normalized['option']['order'][$option->getCode()] = $option->getSortOrder();
            }
        }

        return $normalized;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof AbstractAttribute && 'api_import' === $format;
    }

    /**
     * {@inheritdoc}
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        if (!$serializer instanceof NormalizerInterface) {
            throw new \LogicException('Serializer must be a normalizer');
        }

        $this->normalizer = $serializer;
    }

    /**
     * Returns valid option values (in terms of store view mapping and default locale)
     *
     * @param AttributeOption $option
     * @param array           $context
     *
     * @return array
     */
    protected function getValidOptionValues(AttributeOption $option, array $context)
    {
        $values = [];
        foreach ($option->getOptionValues() as $optionValue) {
            $values = array_merge(
                $values,
                $this->normalizer->normalize($optionValue, 'api_import', $context)
            );
        }

        return $values;
    }
}
