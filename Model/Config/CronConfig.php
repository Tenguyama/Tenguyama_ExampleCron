<?php

namespace Tenguyama\CronExample\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\ValueFactory;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class CronConfig extends Value
{
    const CRON_STRING_PATH = 'crontab/default/jobs/tenguyama_cronexample_cron_reindexmodel/schedule/cron_expr';
    const CRON_MODEL_PATH = 'crontab/default/jobs/tenguyama_cronexample_cron_reindexmodel/run/model';

    protected $_configValueFactory;
    protected $_runModelPath = 'Tenguyama\CronExample\Cron\ReindexModel::execute'; // Вказуємо модель та метод
    protected $_timezone;

    public function __construct(
        \Magento\Framework\Model\Context                        $context,
        \Magento\Framework\Registry                             $registry,
        ScopeConfigInterface                                    $config,
        \Magento\Framework\App\Cache\TypeListInterface          $cacheTypeList,
        ValueFactory                                            $configValueFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb           $resourceCollection = null,
        TimezoneInterface                                       $timezone,
        array                                                   $data = []
    )
    {
        $this->_configValueFactory = $configValueFactory;
        $this->_timezone = $timezone; // Зберігаємо часову зону
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    // Метод, що викликається перед збереженням значення конфігурації
    public function afterSave()
    {
        try {
            $cronTime = $this->getData('groups/general/fields/cron_time/value');
            $frequency = $this->getData('groups/general/fields/frequency/value');

            // Дебаг-логування значень
            $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/cron_debug.log');
            $logger = new \Zend_Log();
            $logger->addWriter($writer);

            $logger->info('cron_time raw value: ' . print_r($cronTime, true));

            // Якщо cron_time — масив, обробляємо його
            if (is_array($cronTime)) {
                // Перетворюємо масив у формат HH:MM
                $cronTime = sprintf('%02d:%02d', (int)$cronTime[0], (int)$cronTime[1]);
                $logger->info('cron_time formatted: ' . $cronTime);
            }

            if (!is_string($cronTime) || empty($cronTime)) {
                throw new \Exception('Invalid cron time format.');
            }
            $timezone = $this->_timezone->getConfigTimezone();
            $logger->info('Timezone: ' . $timezone);
            $dateTime = new \DateTime($cronTime, new \DateTimeZone($timezone));
            $minutes = (int)$dateTime->format('i');
            $hours = (int)$dateTime->format('H');

            $logger->info("Parsed cron time - Hours: $hours, Minutes: $minutes");

            // Визначаємо частоту запуску
            switch ($frequency) {
                case \Magento\Cron\Model\Config\Source\Frequency::CRON_DAILY:
                    $cronExprArray = [$minutes, $hours, '*', '*', '*']; // Кожного дня в вказаний час
                    break;
                case \Magento\Cron\Model\Config\Source\Frequency::CRON_WEEKLY:
                    $cronExprArray = [$minutes, $hours, '*', '*', '1']; // Кожного тижня (думаю в залежності від конфігурації сервера це або понеділок або неділя (в залежності від регіону))
                    break;
                case \Magento\Cron\Model\Config\Source\Frequency::CRON_MONTHLY:
                    $cronExprArray = [$minutes, $hours, '1', '*', '*']; // Раз на місяць
                    break;
                default:
                    $cronExprArray = ['*', '*', '*', '*', '*']; // За замовчуванням: кожну хвилину
            }

            $cronExprString = implode(' ', $cronExprArray);
            $logger->info('Final cron expression: ' . $cronExprString);

            // Збереження cron виразу
            $this->_configValueFactory->create()->load(
                self::CRON_STRING_PATH,
                'path'
            )->setValue($cronExprString)
                ->setPath(self::CRON_STRING_PATH)
                ->save();

            // Оновлення моделі
            $this->_configValueFactory->create()->load(
                self::CRON_MODEL_PATH,
                'path'
            )->setValue($this->_runModelPath)
                ->setPath(self::CRON_MODEL_PATH)
                ->save();

        } catch (\Exception $e) {
            throw new \Exception(__('Error saving cron expression: ' . $e->getMessage()));
        }

        return parent::afterSave();
    }




}
