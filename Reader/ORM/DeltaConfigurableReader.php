<?php

namespace Pim\Bundle\MagentoConnectorBundle\Reader\ORM;

use Doctrine\ORM\EntityManager;
use Pim\Bundle\BaseConnectorBundle\Reader\Doctrine\ORMProductReader;
use Pim\Bundle\CatalogBundle\Manager\ChannelManager;
use Pim\Bundle\CatalogBundle\Manager\CompletenessManager;
use Pim\Bundle\CatalogBundle\Repository\ProductRepositoryInterface;
use Pim\Bundle\MagentoConnectorBundle\Builder\TableNameBuilder;
use Pim\Bundle\TransformBundle\Converter\MetricConverter;

/**
 * Delta reader for configurables
 *
 * @author    Romain Monceau <romain@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class DeltaConfigurableReader extends ORMProductReader
{
    /** @var TableNameBuilder */
    protected $tableNameBuilder;

    /** @var string */
    protected $catalogProductParamName;

    /** @var string */
    protected $categoryParamName;

    /** @var string */
    protected $deltaParamName;

    /** @var string */
    protected $groupTypeParamName;

    /**
     * @param ProductRepositoryInterface $repository
     * @param ChannelManager             $channelManager
     * @param CompletenessManager        $completenessManager
     * @param MetricConverter            $metricConverter
     * @param EntityManager              $entityManager
     * @param boolean                    $missingCompleteness
     * @param TableNameBuilder           $tableNameBuilder
     * @param string                     $catalogProductParamName
     * @param string                     $categoryParamName
     * @param string                     $groupTypeParamName
     * @param string                     $deltaProductParamName
     */
    public function __construct(
        ProductRepositoryInterface $repository,
        ChannelManager $channelManager,
        CompletenessManager $completenessManager,
        MetricConverter $metricConverter,
        EntityManager $entityManager,
        $missingCompleteness = true,
        TableNameBuilder $tableNameBuilder,
        $catalogProductParamName,
        $categoryParamName,
        $groupTypeParamName,
        $deltaParamName
    ) {
        parent::__construct(
            $repository,
            $channelManager,
            $completenessManager,
            $metricConverter,
            $entityManager,
            $missingCompleteness
        );

        $this->tableNameBuilder        = $tableNameBuilder;
        $this->catalogProductParamName = $catalogProductParamName;
        $this->categoryParamName       = $categoryParamName;
        $this->groupTypeParamName      = $groupTypeParamName;
        $this->deltaParamName          = $deltaParamName;
    }

    /**
     * {@inheritdoc}
     */
    protected function getIds()
    {
        if (!is_object($this->channel)) {
            $this->channel = $this->channelManager->getChannelByCode($this->channel);
        }

        if ($this->missingCompleteness) {
            $this->completenessManager->generateMissingForChannel($this->channel);
        }

        $treeId = $this->channel->getCategory()->getId();
        $sql = $this->getSQLQuery($this->channel->getId(), $treeId, $this->getJobInstance()->getId());

        $connection = $this->entityManager->getConnection();
        $results = $connection->fetchAll($sql);

        $productIds = [];
        foreach ($results as $result) {
            $productIds[] = $result['id'];
        }

        return $productIds;
    }

    /**
     * @param int $channelId
     * @param int $treeId
     * @param int $jobInstanceId
     *
     * @return string
     */
    protected function getSQLQuery($channelId, $treeId, $jobInstanceId)
    {
        $productTable = $this->tableNameBuilder->getTableName($this->catalogProductParamName);
        $completenessesTable = $this->tableNameBuilder->getTableName(
            $this->catalogProductParamName,
            'completenesses'
        );
        $categoryProductTable = $this->tableNameBuilder->getTableName(
            $this->catalogProductParamName,
            'categories',
            true
        );
        $groupTable = $this->tableNameBuilder->getTableName(
            $this->catalogProductParamName,
            'groups',
            false
        );
        $groupProductTable = $this->tableNameBuilder->getTableName(
            $this->catalogProductParamName,
            'groups',
            true
        );
        $groupTypeTable = $this->tableNameBuilder->getTableName($this->groupTypeParamName);
        $categoryTable  = $this->tableNameBuilder->getTableName($this->categoryParamName);
        $deltaConfigurableExportTable = $this->tableNameBuilder->getTableName($this->deltaParamName);

        return <<<SQL
            SELECT p.id FROM $productTable p
            INNER JOIN $completenessesTable comp
                ON comp.product_id = p.id AND comp.channel_id = $channelId AND comp.ratio = 100
            INNER JOIN $categoryProductTable cp ON p.id = cp.product_id
            INNER JOIN $categoryTable c ON c.id = cp.category_id AND c.root = $treeId

            INNER JOIN $groupProductTable gp ON gp.product_id = p.id
            INNER JOIN $groupTable g ON g.id = gp.group_id
            INNER JOIN $groupTypeTable gt ON gt.id = g.type_id AND gt.is_variant = 1

            LEFT JOIN $deltaConfigurableExportTable de ON de.product_id = p.id
            LEFT JOIN akeneo_batch_job_instance j ON j.id = de.job_instance_id AND j.id = $jobInstanceId

            WHERE p.updated > de.last_export OR de.last_export IS NULL
            AND p.is_enabled = 1

            GROUP BY p.id
SQL;
    }

    /**
     * @return \Akeneo\Bundle\BatchBundle\Entity\JobInstance
     */
    protected function getJobInstance()
    {
        return $this->stepExecution->getJobExecution()->getJobInstance();
    }
}
