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
declare(strict_types=1);

namespace Mageplaza\Smtp\Test\Unit\Observer\Customer;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Observer\Customer\ModelSaveBefore;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class ModelSaveBeforeTest
 * @package Mageplaza\Smtp\Test\Unit\Observer\Customer
 */
class ModelSaveBeforeTest extends TestCase
{
    /**
     * @var CustomerFactory|MockObject
     */
    private CustomerFactory|MockObject $customerFactoryMock;

    /**
     * @var EmailMarketing|MockObject
     */
    private EmailMarketing|MockObject $helperMock;

    /**
     * @var ModelSaveBefore
     */
    private ModelSaveBefore $observer;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->customerFactoryMock = $this->getMockBuilder(CustomerFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $this->helperMock = $this->createMock(EmailMarketing::class);

        $this->observer = new ModelSaveBefore($this->customerFactoryMock, $this->helperMock);
    }

    /**
     * Enable the email-marketing gate (feature on + credentials present).
     */
    private function enableGate(): void
    {
        $this->helperMock->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperMock->method('getSecretKey')->willReturn('secret');
        $this->helperMock->method('getAppID')->willReturn('app');
    }

    /**
     * @param DataObject $dataObject
     *
     * @return Observer|MockObject
     */
    private function observerWithDataObject(DataObject $dataObject): Observer
    {
        $event    = new Event(['data_object' => $dataObject]);
        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }

    /**
     * When the feature is disabled nothing is touched.
     */
    public function testExecuteDoesNothingWhenDisabled(): void
    {
        $this->helperMock->method('isEnableEmailMarketing')->willReturn(false);
        $this->customerFactoryMock->expects($this->never())->method('create');

        $object = new DataObject();
        $this->observer->execute($this->observerWithDataObject($object));

        $this->assertNull($object->getData('is_new_record'));
    }

    /**
     * A record without an id is flagged as new.
     */
    public function testExecuteMarksNewRecord(): void
    {
        $this->enableGate();
        $this->customerFactoryMock->expects($this->never())->method('create');

        $object = new DataObject();
        $this->observer->execute($this->observerWithDataObject($object));

        $this->assertTrue($object->getData('is_new_record'));
    }

    /**
     * An existing Customer gets its original snapshot loaded and attached.
     */
    public function testExecuteLoadsOriginalCustomer(): void
    {
        $this->enableGate();

        /** @var Customer $customer */
        $customer = (new ObjectManager($this))->getObject(Customer::class);
        $customer->setId(5);

        $origCustomer = $this->createMock(Customer::class);
        $loadedMock   = $this->createMock(Customer::class);
        $loadedMock->expects($this->once())->method('load')->with(5)->willReturn($origCustomer);
        $this->customerFactoryMock->expects($this->once())->method('create')->willReturn($loadedMock);

        $this->observer->execute($this->observerWithDataObject($customer));

        $this->assertSame($origCustomer, $customer->getData('custom_orig_object'));
    }
}
