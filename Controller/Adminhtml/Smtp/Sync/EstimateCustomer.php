<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Smtp
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Smtp\Controller\Adminhtml\Smtp\Sync;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;

/**
 * Class EstimateCustomer
 * @package Mageplaza\Smtp\Controller\Adminhtml\Smtp\Sync
 */
class EstimateCustomer extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Mageplaza_Smtp::smtp';

    /**
     * @var CustomerCollectionFactory
     */
    protected $customerCollectionFactory;

    /**
     * EstimateCustomer constructor.
     *
     * @param Context $context
     * @param CustomerCollectionFactory $customerCollectionFactory
     */
    public function __construct(
        Context $context,
        CustomerCollectionFactory $customerCollectionFactory
    ) {
        $this->customerCollectionFactory = $customerCollectionFactory;

        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {
            $attribute          = 'mp_smtp_is_synced';
            $customerCollection = $this->customerCollectionFactory->create();
            $storeId = $this->getRequest()->getParam('storeId');
            $websiteId = $this->getRequest()->getParam('websiteId');
            if ($storeId) {
                $customerCollection->addFieldToFilter('store_id', $storeId);
            }

            if ($websiteId) {
                $customerCollection->addFieldToFilter('website_id', $websiteId);
            }

            $ids = $customerCollection->addFieldToFilter($attribute, ['null' => 1])
                ->getAllIds();

            $result['ids']   = $ids;
            $result['total'] = count($ids);

            if ($result['total'] === 0) {
                $result['message'] = __('No customers to synchronize.');
            }

            $result['status'] = true;

        } catch (Exception $e) {
            $result = [
                'status'  => false,
                'message' => $e->getMessage()
            ];
        }

        return $this->getResponse()->representJson(EmailMarketing::jsonEncode($result));
    }
}
