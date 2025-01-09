<?php


namespace BronzeByte\CustomOrderNumber\Helper;

class Config extends \Magento\Framework\App\Helper\AbstractHelper
{
  /**
     * @var \Magento\Store\Model\StoreManagerInterface
     * Used to manage and fetch store-related data.
     */
    protected $storeManager;

    /**
     * @var \Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory
     * Factory for retrieving configuration data directly from the database.
     */
    protected $coreDataCollectionFactory;

    /**
     * @var Magento\Framework\App\Config\ValueFactory
     * Factory for creating and saving configuration values.
     */
    protected $coreValueFactory;

    /**
     * Constructor to initialize dependencies.
     * 
     * @param \Magento\Framework\App\Helper\Context $context Provides the helper context.
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager Manages store data.
     * @param \Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory $coreDataCollectionFactory Retrieves config data without caching.
     * @param \Magento\Framework\App\Config\ValueFactory $coreValueFactory Creates config values for saving to the database.
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory $coreDataCollectionFactory,
        \Magento\Framework\App\Config\ValueFactory $coreValueFactory
    ) {
        // Assign dependencies to class properties.
        $this->storeManager = $storeManager;
        $this->coreDataCollectionFactory = $coreDataCollectionFactory;
        $this->coreValueFactory = $coreValueFactory;
        // Call parent constructor for context setup.
        parent::__construct($context);
    }


     public function isModuleEnabled()
    {
        // Get the current store ID.
        $storeId = $this->storeManager->getStore()->getId();
        // Retrieve the 'enabled' configuration value for the module.
        return $this->scopeConfig->getValue(
            'custom_order_number/general/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }



     public function isSupportedEntityType($entityType)
    {
        // Define supported entity types.
        $supportedEntityTypes = ['order', 'invoice', 'shipment', 'creditmemo'];
        // Check if the given type is in the list of supported types.
        return in_array($entityType, $supportedEntityTypes);
    }
    

    public function getConfigValue($entityType, $field, $storeId)
    {
        return $this->scopeConfig->getValue(
            'custom_order_number/' . $entityType . '/' . $field,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }



    public function prepareIntPositive($val)
    {
        // Convert the value to an integer.
        $val = intval($val);
        // Ensure it is positive or return 0.
        if ($val < 0) {
            return 0;
        } else {
            return $val;
        }
    }


   public function getConfigFlag($entityType, $field, $storeId)
    {
        return $this->scopeConfig->isSetFlag(
            'custom_order_number/' . $entityType . '/' . $field,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    /**
     * Fetches configuration values directly from the database, bypassing the cache.
     * 
     * @param string $entityType The entity type.
     * @param string $field The field name.
     * @param int $storeId The store ID.
     * @return mixed Configuration data from the database or a newly created config object.
     */
    public function getConfigValueFromDb($entityType, $field, $storeId)
    {
        // Determine the scope of the configuration.
        $scopeId = 0;
        $scope = 'default';

        // Check if configuration should be unique per store.
        if ($this->getConfigFlag($entityType, 'unique_per_store', $storeId)) {
            $scopeId = $storeId;
            $scope = 'stores';
        }
        // Check if configuration should be unique per website.
        if ($this->getConfigFlag($entityType, 'unique_per_website', $storeId)) {
            $scopeId = $this->storeManager->getStore($storeId)->getWebsiteId();
            $scope = 'websites';
        }

        // Retrieve the configuration data using the collection factory.
        $configDataCollection = $this->coreDataCollectionFactory->create()
            ->addFieldToFilter('path', 'custom_order_number/' . $entityType . '/' . $field)
            ->addFieldToFilter('scope', $scope)
            ->addFieldToFilter('scope_id', $scopeId)
            ->setPageSize(1);

        // Return the first item if found; otherwise, create a new config object.
        if ($configDataCollection->count() > 0) {
            return $configDataCollection->getFirstItem();
        } else {
            $configData = $this->coreValueFactory->create()
                ->setPath('custom_order_number/' . $entityType . '/' . $field)
                ->setScope($scope)
                ->setScopeId($scopeId);
            return $configData;
        }
    }

}