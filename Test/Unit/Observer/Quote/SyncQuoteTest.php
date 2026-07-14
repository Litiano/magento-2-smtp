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

namespace Mageplaza\Smtp\Test\Unit\Observer\Quote;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\ObjectManagerInterface;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Observer\Quote\SyncQuote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class SyncQuoteTest
 * @package Mageplaza\Smtp\Test\Unit\Observer\Quote
 */
class SyncQuoteTest extends TestCase
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
     * @var SyncQuote
     */
    private SyncQuote $observer;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->helperMock = $this->createMock(EmailMarketing::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->observer   = new SyncQuote($this->helperMock, $this->loggerMock);

        // EmailMarketing::jsonEncode()/jsonDecode() resolve the Json helper through
        // the global ObjectManager; stub it so the static call does not fall back.
        $jsonHelper = $this->createMock(JsonHelper::class);
        $jsonHelper->method('jsonEncode')->willReturnCallback(static fn($v) => json_encode($v));
        $jsonHelper->method('jsonDecode')->willReturnCallback(static fn($v) => json_decode($v, true));
        $om = $this->createMock(ObjectManagerInterface::class);
        $om->method('get')->willReturn($jsonHelper);
        ObjectManager::setInstance($om);
    }

    /**
     * @param DataObject $quote
     *
     * @return Observer|MockObject
     */
    private function observerWithQuote(DataObject $quote): Observer
    {
        $event    = new Event(['quote' => $quote]);
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
     * With the feature off, the quote is never inspected.
     */
    public function testExecuteSkipsWhenDisabled(): void
    {
        $this->helperMock->method('isEnableEmailMarketing')->willReturn(false);
        $this->helperMock->expects($this->never())->method('getACEData');

        $this->observer->execute($this->observerWithQuote(new DataObject(['items_count' => 1])));
    }

    /**
     * A changed, non-empty cart is persisted and pushed to the checkout endpoint.
     */
    public function testExecuteSyncsChangedQuote(): void
    {
        $this->enableGate();
        $quote = new DataObject(['id' => 7, 'items_count' => 2]);

        $this->helperMock->method('getACEData')->with($quote)->willReturn(['cart' => 'data']);

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('update');

        // getResourceQuote() returns the quote resource used for the direct update.
        $resourceQuote = $this->getMockBuilder(\Magento\Quote\Model\ResourceModel\Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable'])
            ->getMock();
        $resourceQuote->method('getConnection')->willReturn($connection);
        $resourceQuote->method('getMainTable')->willReturn('quote');
        $this->helperMock->method('getResourceQuote')->willReturn($resourceQuote);

        $this->helperMock->expects($this->once())
            ->method('sendRequestWithoutWaitResponse')
            ->with(['cart' => 'data'], EmailMarketing::CHECKOUT_URL);

        $this->observer->execute($this->observerWithQuote($quote));
    }

    /**
     * An empty cart with no prior log data is not synced.
     */
    public function testExecuteSkipsEmptyCart(): void
    {
        $this->enableGate();
        $this->helperMock->expects($this->never())->method('getACEData');

        $this->observer->execute($this->observerWithQuote(new DataObject(['items_count' => 0])));
    }

    /**
     * Exceptions raised while syncing are logged.
     */
    public function testExecuteLogsException(): void
    {
        $this->enableGate();
        $quote = new DataObject(['id' => 7, 'items_count' => 2]);
        $this->helperMock->method('getACEData')->willThrowException(new \Exception('fail'));

        $this->loggerMock->expects($this->once())->method('critical')->with('fail');

        $this->observer->execute($this->observerWithQuote($quote));
    }
}
