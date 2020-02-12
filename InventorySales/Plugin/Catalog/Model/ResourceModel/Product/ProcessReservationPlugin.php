<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\Catalog\Model\ResourceModel\Product;

use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Framework\Amqp\Config;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Model\AbstractModel;

/**
 * Process reservations after product save plugin.
 */
class ProcessReservationPlugin
{
    /**
     * @var PublisherInterface
     */
    private $publisher;

    /**
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @param PublisherInterface $publisher
     * @param DeploymentConfig $deploymentConfig
     */
    public function __construct(PublisherInterface $publisher, DeploymentConfig $deploymentConfig)
    {
        $this->publisher = $publisher;
        $this->deploymentConfig = $deploymentConfig;
    }

    /**
     * Asynchronously update reservations in case product sku has been changed.
     *
     * @param Product $subject
     * @param Product $result
     * @param AbstractModel $product
     * @return Product
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterSave(
        Product $subject,
        Product $result,
        AbstractModel $product
    ): Product {
        $origSku = $product->getOrigData('sku');
        if ($origSku !== null && $origSku !== $product->getSku()) {
            try {
                $configData = $this->deploymentConfig->getConfigData(Config::QUEUE_CONFIG) ?: [];
            } catch (FileSystemException|RuntimeException $e) {
                $configData = [];
            }
            $topic = isset($configData[Config::AMQP_CONFIG][Config::HOST])
                ? 'async.inventory.reservations.update'
                : 'async.inventory.reservations.update.db';
            $this->publisher->publish($topic, [(string)$origSku, (string)$product->getSku()]);
        }

        return $result;
    }
}
