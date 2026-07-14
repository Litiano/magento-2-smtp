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

namespace Mageplaza\Smtp\Test\Unit\Observer\Order;

use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as ResourceOrder;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Observer\Order\OrderComplete;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class OrderCompleteTest
 * @package Mageplaza\Smtp\Test\Unit\Observer\Order
 */
class OrderCompleteTest extends TestCase
{
    /**
     * @var EmailMarketing|MockObject
     */
    private EmailMarketing|MockObject $helperMock;

    /**
     * @var LoggerInterface|MockObject
     */
    private LoggerInterface|MockObject $loggerMock;

    /**
     * @var ResourceOrder|MockObject
     */
    private ResourceOrder|MockObject $resourceOrderMock;

    /**
     * @var OrderComplete
     */
    private OrderComplete $observer;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->helperMock        = $this->createMock(EmailMarketing::class);
        $this->loggerMock        = $this->createMock(LoggerInterface::class);
        $this->resourceOrderMock = $this->getMockBuilder(ResourceOrder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable'])
            ->getMock();

        $this->observer = new OrderComplete(
            $this->helperMock,
            $this->loggerMock,
            $this->resourceOrderMock
        );
    }

    /**
     * @param DataObject $order
     *
     * @return Observer|MockObject
     */
    private function observerWithOrder(DataObject $order): Observer
    {
        $event    = new Event(['order' => $order]);
        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }

    /**
     * Enable the email-marketing gate.
     */
    private function enableGate(): void
    {
        $this->helperMock->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperMock->method('getSecretKey')->willReturn('secret');
        $this->helperMock->method('getAppID')->willReturn('app');
    }

    /**
     * With the feature off, no order is synced.
     */
    public function testExecuteSkipsWhenDisabled(): void
    {
        $this->helperMock->method('isEnableEmailMarketing')->willReturn(false);
        $this->helperMock->expects($this->never())->method('sendOrderRequest');

        $this->observer->execute($this->observerWithOrder(new DataObject()));
    }

    /**
     * A brand new order (created == updated, not yet flagged) is synced once,
     * its customer is refreshed and the "created" flag is persisted.
     */
    public function testExecuteSyncsNewOrder(): void
    {
        $this->enableGate();

        $order = new DataObject([
            'entity_id'   => 100,
            'created_at'  => '2026-07-14 10:00:00',
            'updated_at'  => '2026-07-14 10:00:00',
            'state'       => Order::STATE_PROCESSING,
            'customer_id' => 55,
        ]);

        $this->helperMock->expects($this->once())->method('sendOrderRequest')->with($order);
        $this->helperMock->expects($this->once())->method('updateCustomer')->with(55);

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('update');
        $this->resourceOrderMock->method('getConnection')->willReturn($connection);
        $this->resourceOrderMock->method('getMainTable')->willReturn('sales_order');

        $this->observer->execute($this->observerWithOrder($order));

        $this->assertTrue($order->getData('mp_smtp_email_marketing_order_created'));
    }

    /**
     * A completed order that has not been synced is pushed to the complete endpoint.
     */
    public function testExecuteSendsCompletedOrder(): void
    {
        $this->enableGate();

        // Already flagged as created so the first branch is skipped; created != updated.
        $order = new DataObject([
            'entity_id'                              => 101,
            'created_at'                             => '2026-07-14 10:00:00',
            'updated_at'                             => '2026-07-14 12:00:00',
            'state'                                  => Order::STATE_COMPLETE,
            'mp_smtp_email_marketing_order_created'  => true,
        ]);

        $this->helperMock->expects($this->once())
            ->method('sendOrderRequest')
            ->with($order, EmailMarketing::ORDER_COMPLETE_URL);

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('update');
        $this->resourceOrderMock->method('getConnection')->willReturn($connection);
        $this->resourceOrderMock->method('getMainTable')->willReturn('sales_order');

        $this->observer->execute($this->observerWithOrder($order));
    }

    /**
     * Exceptions raised while syncing are caught and logged.
     */
    public function testExecuteLogsException(): void
    {
        $this->enableGate();
        $order = new DataObject([
            'entity_id'  => 102,
            'created_at' => '2026-07-14 10:00:00',
            'updated_at' => '2026-07-14 10:00:00',
            'state'      => Order::STATE_PROCESSING,
        ]);

        // syncOrder() catches its own exception, so force the failure on updateCustomer.
        $this->helperMock->method('updateCustomer')->willThrowException(new \Exception('sync failed'));
        $this->loggerMock->expects($this->atLeastOnce())->method('critical');

        $this->observer->execute($this->observerWithOrder($order));
    }
}
