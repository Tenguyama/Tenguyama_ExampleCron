<?php

namespace Tenguyama\CronExample\Cron;

use Magento\Indexer\Model\IndexerFactory;
use Magento\Indexer\Model\Indexer\CollectionFactory as IndexerCollectionFactory;

class ReindexModel
{
    protected $indexerFactory;
    protected $indexerCollectionFactory;

    public function __construct(
        IndexerFactory $indexerFactory,
        IndexerCollectionFactory $indexerCollectionFactory
    ) {
        $this->indexerFactory = $indexerFactory;
        $this->indexerCollectionFactory = $indexerCollectionFactory;
    }

    public function execute()
    {
        try {
            $currentTime = new \DateTime(); // Поточний час, просто для логування
            // Лог у кастомний файл
            $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/cron-example.log');
            $logger = new \Zend_Log();
            $logger->addWriter($writer);
            $logger->info("Reindex Cron Running with schedule: " . $currentTime->format('Y-m-d H:i:s'));
                // Отримуємо всі індекси
                $indexerCollection = $this->indexerCollectionFactory->create();
                foreach ($indexerCollection as $indexer) {
                    /** @var \Magento\Indexer\Model\Indexer $index */
                    $index = $this->indexerFactory->create()->load($indexer->getId());
                    if (!$index->isValid()) {
                        $index->reindexAll();
                        $logger->info("Reindexed: " . $index->getTitle());
                    }
                }
                $logger->info("Reindex Cron Completed Successfully.");
        } catch (\Exception $e) {
            $logger->error("Reindex Cron Error: " . $e->getMessage());
        }
        return $this;
    }
}
