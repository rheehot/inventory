<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryImportExport\Observer;

use Magento\Framework\Amqp\Config;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\MessageQueue\PublisherInterface;

/**
 * Clean source items after products removed during import observer.
 */
class DeleteSourceItemsAfterProductDeleteObserver implements ObserverInterface
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
     * Asynchronously delete source items after products have been removed during import.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $skus = [];
        $bunch = $observer->getEvent()->getData('bunch');
        foreach ($bunch as $product) {
            if (isset($product['sku'])) {
                $skus[] = $product['sku'];
            }
        }
        try {
            $configData = $this->deploymentConfig->getConfigData(Config::QUEUE_CONFIG) ?: [];
        } catch (FileSystemException|RuntimeException $e) {
            $configData = [];
        }
        $topic = isset($configData[Config::AMQP_CONFIG][Config::HOST])
            ? 'async.inventory.source.items.cleanup'
            : 'async.inventory.source.items.cleanup.db';
        $this->publisher->publish($topic, [$skus]);
    }
}
