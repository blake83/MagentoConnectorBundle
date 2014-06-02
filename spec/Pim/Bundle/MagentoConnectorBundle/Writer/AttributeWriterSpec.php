<?php

namespace spec\Pim\Bundle\MagentoConnectorBundle\Writer;

use Pim\Bundle\CatalogBundle\Entity\AttributeGroup;
use Pim\Bundle\CatalogBundle\Entity\Family;
use Pim\Bundle\MagentoConnectorBundle\Guesser\WebserviceGuesser;
use Pim\Bundle\MagentoConnectorBundle\Manager\AttributeMappingManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\FamilyMappingManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\AttributeGroupMappingManager;
use Pim\Bundle\MagentoConnectorBundle\Webservice\SoapCallException;
use Pim\Bundle\MagentoConnectorBundle\Webservice\Webservice;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParameters;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParametersRegistry;
use Pim\Bundle\CatalogBundle\Model\AbstractAttribute;
use Akeneo\Bundle\BatchBundle\Entity\StepExecution;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class AttributeWriterSpec extends ObjectBehavior
{
    function let(
        WebserviceGuesser                   $webserviceGuesser,
        FamilyMappingManager                $familyMappingManager,
        AttributeMappingManager             $attributeMappingManager,
        AttributeGroupMappingManager        $attributeGroupMappingManager,
        Webservice                          $webservice,
        StepExecution                       $stepExecution,
        MagentoSoapClientParametersRegistry $clientParametersRegistry,
        MagentoSoapClientParameters         $clientParameters
    ) {
        $clientParametersRegistry->getInstance(null, null, null, '/api/soap/?wsdl', 'default', null, null)->willReturn($clientParameters);
        $webserviceGuesser->getWebservice($clientParameters)->willReturn($webservice);

        $this->beConstructedWith(
            $webserviceGuesser,
            $familyMappingManager,
            $attributeMappingManager,
            $attributeGroupMappingManager,
            $clientParametersRegistry
        );
        $this->setStepExecution($stepExecution);
    }

    function it_sends_attribute_to_create_on_magento_webservice(
        $webservice,
        AbstractAttribute $attribute,
        AttributeMappingManager $attributeMappingManager
    ) {
        $attributes = array(
            array(
                $attribute,
                array(
                    'create' => array(
                        'attributeName' => 'attribute_code'
                    ),
                )
            )
        );
        $this->setMagentoUrl(null);
        $this->setWsdlUrl('/api/soap/?wsdl');

        $attribute->getFamilies()->willReturn(array());
        $attribute->getGroup()->willReturn(null);

        $webservice->createAttribute(Argument::any())->willReturn(12);

        $attributeMappingManager->registerAttributeMapping($attribute, 12, '/api/soap/?wsdl')->shouldBeCalled();

        $this->write($attributes);
    }

    function it_sends_attribute_with_group_and_family_to_create_on_magento_webservice(
        $webservice,
        AbstractAttribute            $attribute,
        AttributeMappingManager      $attributeMappingManager,
        AttributeGroupMappingManager $attributeGroupMappingManager,
        FamilyMappingManager         $familyMappingManager,
        AttributeGroup               $group,
        Family                       $family
    ) {
        $attributes = array(
            array(
                $attribute,
                array(
                    'create' => array(
                        'attributeName' => 'attribute_code'
                    ),
                )
            )
        );
        $this->setMagentoUrl(null);
        $this->setWsdlUrl('/api/soap/?wsdl');

        $attribute->getFamilies()->willReturn(array($family));
        $attribute->getGroup()->willReturn($group);

        $group->getCode()->willReturn('group_name');

        $familyMappingManager->getIdFromFamily(Argument::any(), '/api/soap/?wsdl')->willReturn(414);

        $webservice->addAttributeGroupToAttributeSet(414, 'group_name')->shouldBeCalled()->willReturn(797);
        $webservice->createAttribute(Argument::any())->willReturn(12);
        $webservice->addAttributeToAttributeSet(12, 414, 797)->shouldBeCalled();

        $attributeGroupMappingManager->registerGroupMapping($group, $family, 797, '/api/soap/?wsdl')->shouldBeCalled();
        $attributeGroupMappingManager->getIdFromGroup($group, $family, '/api/soap/?wsdl')->willReturn(797);

        $attributeMappingManager->registerAttributeMapping($attribute, 12, '/api/soap/?wsdl')->shouldBeCalled();

        $this->write($attributes);
    }

    function it_increments_summary_info_if_group_already_exists(
        $webservice,
        AttributeMappingManager      $attributeMappingManager,
        AbstractAttribute            $attribute,
        AttributeGroup               $group,
        FamilyMappingManager         $familyMappingManager,
        AttributeGroupMappingManager $attributeGroupMappingManager,
        StepExecution                $stepExecution,
        Family                       $family
    ) {
        $attributes = array(
            array(
                $attribute,
                array(
                    'create' => array(
                        'attributeName' => 'attribute_code'
                    ),
                )
            )
        );
        $this->setMagentoUrl(null);
        $this->setWsdlUrl('/api/soap/?wsdl');

        $attribute->getFamilies()->willReturn(array($family));
        $attribute->getGroup()->willReturn($group);

        $group->getCode()->willReturn('group_name');

        $familyMappingManager->getIdFromFamily($family, '/api/soap/?wsdl')->willReturn(414);

        $webservice->addAttributeGroupToAttributeSet('414', 'group_name')->willThrow(new SoapCallException('Group already exists.'));
        $webservice->createAttribute(Argument::any())->willReturn(12);
        $webservice->addAttributeToAttributeSet(12, 414, 797)->shouldBeCalled();

        $attributeGroupMappingManager->registerGroupMapping(Argument::cetera())->shouldNotBeCalled();
        $attributeGroupMappingManager->getIdFromGroup(Argument::any(), $family, '/api/soap/?wsdl')->willReturn(797);

        $stepExecution->incrementSummaryInfo('Group was already in attribute set on magento')->shouldBeCalled();
        $stepExecution->incrementSummaryInfo('Attributes created')->shouldBeCalled();

        $attributeMappingManager->registerAttributeMapping($attribute, 12, '/api/soap/?wsdl')->shouldBeCalled();

        $this->write($attributes);
    }
}