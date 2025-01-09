<?php

namespace BronzeByte\CustomOrderNumber\Helper;

class Generator extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $configHelper;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \BronzeByte\CustomOrderNumber\Logger\Logger
     */
    protected $logger;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $coreDate;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * Generator constructor.
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param Config $configHelper
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \BronzeByte\CustomOrderNumber\Logger\Logger $logger
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $coreDate
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \BronzeByte\CustomOrderNumber\Helper\Config $configHelper,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \BronzeByte\CustomOrderNumber\Logger\Logger $logger,
        \Magento\Framework\Stdlib\DateTime\DateTime $coreDate
    ) {
        parent::__construct($context);
        $this->configHelper = $configHelper;
        $this->objectManager = $objectManager;
        $this->logger = $logger;
        $this->coreDate = $coreDate;
        $this->eventManager = $context->getEventManager();
    }

    /**
     * Generate increment ID for orders/invoices/shipment/credit memos
     *
     * @param Sequence $subject
     * @param \Closure $proceed
     * @return mixed
     */
    public function generateIncrementId($object, $entityType, $originalSequence)
    {
        $storeId = $object->getStoreId();

        if (!$this->configHelper->isModuleEnabled()) {
            return $originalSequence;
        }

        try {
            if (!$originalSequence) {
                // There was a problem. Don't hook in either.
                return $originalSequence;
            }

            // Is the module enabled?
            if (!$this->configHelper->isModuleEnabled()) {
                return $originalSequence;
            }

            // Is supported entity_type?
            if (!$this->configHelper->isSupportedEntityType($entityType)) {
                return $originalSequence;
            }

            // Is (order/invoice/...) number customizer enabled for this store ID?
            if (!$this->configHelper->getConfigFlag($entityType, 'enabled', $storeId)) {
                return $originalSequence;
            }

            // Shall we create a new number or will the order number be used instead?
            // Just for invoice/shipment/credit memo numbers
            if ($entityType == 'invoice' || $entityType == 'shipment' || $entityType == 'creditmemo') {
                if ($this->configHelper->getConfigFlag($entityType, 'same_as_order', $storeId)) {
                    return $originalSequence;
                }
            }

            $newIncrementId = $this->generateCustomIncrementId($entityType, $storeId);
            if (!$newIncrementId || empty($newIncrementId)) {
                $this->logger->warning(
                    sprintf(
                        'Attention: For %1 ID %2, an empty increment ID was generated: %3',
                        $entityType,
                        $originalSequence,
                        $newIncrementId
                    )
                );
                return $originalSequence;
            }

            // Check if increment ID exists already, if yes return non-existing increment ID
            if ($this->isIncrementIdExisting($entityType, $newIncrementId)) {
                $this->logger->warning(
                    sprintf(
                        'Attention: Generated %1 increment_id "%2" already exists. Using Magento increment_id "%3" instead.',
                        $entityType,
                        $newIncrementId,
                        $originalSequence
                    )
                );
                return $originalSequence;
            }
        } catch (\Exception $e) {
            $this->logger->alert('Exception while generating new increment ID: '. $e->getMessage(). ' - ' . $e->getTraceAsString());
            return $originalSequence;
        }

        return $newIncrementId;
    }


    protected function isIncrementIdExisting($entityType, $incrementId)
    {
        if ($entityType == \Magento\Sales\Model\Order::ENTITY) {
            $entity = '\Magento\Sales\Model\Order';
        } else {
            if ($entityType == 'invoice') {
                $entity = '\Magento\Sales\Model\Order\Invoice';
            } else {
                if ($entityType == 'shipment') {
                    $entity = '\Magento\Sales\Model\Order\Shipment';
                } else {
                    if ($entityType == 'creditmemo') {
                        $entity = '\Magento\Sales\Model\Order\Creditmemo';
                    } else {
                        $this->logger->warning(
                            __('Attention: Specified entity %1 is not supported by the extension.', $entityType)
                        );
                        return true;
                    }
                }
            }
        }

        // Check if increment ID exists
        $objectIds = $this->objectManager->create($entity)
            ->getCollection()
            ->addAttributeToFilter('increment_id', $incrementId)
            ->getAllIds();
        if (!empty($objectIds)) {
            return true;
        }
        return false;
    }

protected function generateCustomIncrementId($entityType, $storeId)  
{  
    // Retrieve the increment ID format from the configuration  
    $incrementIdFormat = $this->configHelper->getConfigValue(  
        $entityType,  
        'id_format',  
        $storeId  
    ); // Increment ID format  

    // Get the value to increment the counter by  
    $incrementBy = $this->configHelper->prepareIntPositive(  
        $this->configHelper->getConfigValue($entityType, 'increment_by', $storeId)  
    ); // Increase counter by X  

    // Get the padding length for the counter  
    $incrementPadding = $this->configHelper->prepareIntPositive(  
        $this->configHelper->getConfigValue($entityType, 'padding', $storeId)  
    ); // Counter padding  

    // Determine the reset condition for the counter (daily, weekly, etc.)  
    $resetCounter = $this->configHelper->getConfigValue(  
        $entityType,  
        'reset_counter',  
        $storeId  
    ); // Don't reset, daily, weekly, ...  

    // Get the last reset date from the database  
    $lastResetDate = $this->configHelper->getConfigValueFromDb(  
        $entityType,  
        'reset_date',  
        $storeId  
    ); // Last reset date  

    // Get the starting value for counting  
    $countFromValue = $this->configHelper->prepareIntPositive(  
        $this->configHelper->getConfigValue($entityType, 'count_from', $storeId)  
    ); // Start counting from...  

    // Check if a force reset of the counter is requested  
    $forceResetCounterNow = $this->configHelper->getConfigValueFromDb(  
        $entityType,  
        'force_reset_counter',  
        $storeId  
    );  

    // Retrieve the current increment counter value from the database  
    $incrementCounter = $this->configHelper->getConfigValueFromDb(  
        $entityType,  
        'increment_counter',  
        $storeId  
    );  

    // Prepare the current counter value  
    $currentCounterValue = $this->configHelper->prepareIntPositive($incrementCounter->getValue());  

    // Check if the increment counter exists and is greater than 0  
    if ($incrementCounter && $currentCounterValue > 0) {  
        $lastResetDateValue = $lastResetDate->getValue();  

        // Check if the counter needs to be reset based on the configured reset condition  
        if ($resetCounter !== '' && !empty($lastResetDateValue)) {  
            $dateFormat = false;  

            // Determine the date format based on the reset condition  
            if ($resetCounter == "daily") {  
                $dateFormat = "Y-m-d";  
            } elseif ($resetCounter == "monthly") {  
                $dateFormat = "Y-m";  
            } elseif ($resetCounter == "yearly") {  
                $dateFormat = "Y";  
            }  

            // If a date format is set, check if the date has changed  
            if ($dateFormat) {  
                $dateHasChanged = false;  

                // Compare the current date with the last reset date  
                if ($this->coreDate->date($dateFormat) != $this->coreDate->date($dateFormat, $lastResetDateValue)) {  
                    $dateHasChanged = true;  
                }  

                // Reset the counter if the date has changed  
                if ($dateHasChanged) {  
                    $currentCounterValue = $countFromValue;  
                }  
            }  
        }  

        // Ensure the increment value is at least 1  
        if ($incrementBy < 1) {  
            $incrementBy = 1;  
        }  

        // Calculate the new counter value  
        $newCounterValue = $currentCounterValue + $incrementBy;  

        // If forced reset is requested, reset the counter to the starting value  
        if ($forceResetCounterNow->getValue() === '1') {  
            $newCounterValue = $countFromValue;  
            $forceResetCounterNow->setValue('')->save(); // Clear the force reset flag  
        }  
    } else {  
        // If the counter does not exist or is not greater than 0, start from the count_from value  
        $newCounterValue = $countFromValue;  
    }  

    // Save the current date as the last reset date  
    $dateToday = $this->coreDate->date("Y-m-d");  
    $lastResetDate->setValue($dateToday)->save();  

    // Update the increment counter in the database  
    $incrementCounter->setValue($newCounterValue)->save();  

    // Apply padding to the new counter value if specified  
    if ($incrementPadding > 0) {  
        $newCounterValue = str_pad($newCounterValue, $incrementPadding, 0, STR_PAD_LEFT);  
    }  

    // Define the variables that can be replaced in the increment ID format  
    $replaceableVariables = [  
        '/%d%/' => $this->coreDate->date('j'), // Day of the month without leading zeros  
        '/%dd%/' => $this->coreDate->date('d'), // Day of the month with leading zeros  
        '/%m%/' => $this->coreDate->date('n'), // Month without leading zeros  
        '/%mm%/' => $this->coreDate->date('m'), // Month with leading zeros  
        '/%yy%/' => $this->coreDate->date('y'), // Year in two digits  
        '/%yyyy%/' => $this->coreDate->date('Y'), // Year in four digits  
        '/%h%/' => $this->coreDate->date('G'), // Hour in 24-hour format without leading zeros  
        '/%hh%/' => $this->coreDate->date('H'), // Hour in 24-hour format with leading zeros  
        '/%ii%/' => $this->coreDate->date('i'), // Minutes with leading zeros  
        '/%ss%/' => $this->coreDate->date('s'), // Seconds with leading zeros  
        '/%store_id%/' => $storeId, // Store ID  
        '/%counter%/' => $newCounterValue, // New counter value  
        // Random number placeholders  
        '/%rand3%/' => rand(100, 999),  
        '/%rand4%/' => rand(1000, 9999),  
        '/%rand5%/' => rand(10000, 99999),  
        '/%rand6%/' => rand(100000, 999999),  
        '/%rand7%/' => rand(1000000, 9999999),  
        '/%rand8%/' => rand(10000000, 99999999),  
        '/%rand9%/' => rand(100000000, 999999999),  
    ];  

    // Create a transport object to allow adding custom variables to the increment ID format  
    $transportObject = new \Magento\Framework\DataObject;  
    $transportObject->setCustomVariables([]); // Initialize custom variables  
    $transportObject->setExistingVariables($replaceableVariables); // Set existing variables  

    // Dispatch an event to allow other modules to modify the replaceable variables  
    $this->eventManager->dispatch(  
        'magesales_customordernumber_replace_variables_before',  
        ['transport' => $transportObject]  
    );  

    // Merge any custom variables added by observers into the existing variables  
    $replaceableVariables = array_merge($replaceableVariables, $transportObject->getCustomVariables());  

    // Generate the new increment ID by replacing variables in the format string  
    $newIncrementId = preg_replace(  
        array_keys($replaceableVariables),  
        array_values($replaceableVariables),  
        $incrementIdFormat  
    );  

    // Return the newly generated increment ID  
    return $newIncrementId;  
 }
  }
