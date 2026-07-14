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

use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Observer\Quote\DeleteQuote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class DeleteQuoteTest
 * @package Mageplaza\Smtp\Test\Unit\Observer\Quote
 */
class DeleteQuoteTest extends TestCase
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
     * @var DeleteQuote
     */
    private DeleteQuote $observer;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->helperMock = $this->createMock(EmailMarketing::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->observer = new DeleteQuote($this->helperMock, $this->loggerMock);
    }

    /**
     * @param DataObject $quote
     *
     * @return Observer|MockObject
     */
    private function observerWithQuote(DataObject $quote): Observer
    {
        $event    = new Event(['data_object' => $quote]);
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
     * With the feature off, no delete request is issued.
     */
    public function testExecuteSkipsWhenDisabled(): void
    {
        $this->helperMock->method('isEnableEmailMarketing')->willReturn(false);
        $this->helperMock->expects($this->never())->method('deleteQuote');

        $this->observer->execute($this->observerWithQuote(new DataObject(['id' => 10])));
    }

    /**
     * A persisted quote triggers a delete request with its id and store id.
     */
    public function testExecuteDeletesQuote(): void
    {
        $this->enableGate();
        $quote = new DataObject(['id' => 10, 'store_id' => 2]);

        $this->helperMock->expects($this->once())
            ->method('deleteQuote')
            ->with(10, 2);

        $this->observer->execute($this->observerWithQuote($quote));
    }

    /**
     * A quote that was never saved (no id) is ignored.
     */
    public function testExecuteIgnoresQuoteWithoutId(): void
    {
        $this->enableGate();
        $this->helperMock->expects($this->never())->method('deleteQuote');

        $this->observer->execute($this->observerWithQuote(new DataObject()));
    }

    /**
     * Any exception is caught and logged rather than bubbling up.
     */
    public function testExecuteLogsException(): void
    {
        $this->enableGate();
        $quote = new DataObject(['id' => 10, 'store_id' => 2]);

        $this->helperMock->method('deleteQuote')
            ->willThrowException(new \Exception('boom'));
        $this->loggerMock->expects($this->once())->method('critical')->with('boom');

        $this->observer->execute($this->observerWithQuote($quote));
    }
}
