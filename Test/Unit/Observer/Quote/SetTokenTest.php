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
use Magento\Framework\Math\Random;
use Mageplaza\Smtp\Observer\Quote\SetToken;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class SetTokenTest
 * @package Mageplaza\Smtp\Test\Unit\Observer\Quote
 */
class SetTokenTest extends TestCase
{
    /**
     * @var Random|MockObject
     */
    private Random|MockObject $randomMock;

    /**
     * @var SetToken
     */
    private SetToken $observer;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->randomMock = $this->createMock(Random::class);
        $this->observer   = new SetToken($this->randomMock);
    }

    /**
     * Build an Observer whose event carries the given quote.
     *
     * @param DataObject $quote
     *
     * @return Observer|MockObject
     */
    private function observerWithQuote(DataObject $quote): Observer
    {
        // getQuote() is a magic accessor on the real Event object.
        $event    = new Event(['quote' => $quote]);
        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }

    /**
     * A quote without a token gets a freshly generated unique hash.
     */
    public function testExecuteSetsTokenWhenMissing(): void
    {
        $quote = new DataObject();
        $this->randomMock->expects($this->once())
            ->method('getUniqueHash')
            ->willReturn('generated-hash');

        $this->observer->execute($this->observerWithQuote($quote));

        $this->assertSame('generated-hash', $quote->getData('mp_smtp_ace_token'));
    }

    /**
     * An existing token must never be overwritten.
     */
    public function testExecuteKeepsExistingToken(): void
    {
        $quote = new DataObject(['mp_smtp_ace_token' => 'existing']);
        $this->randomMock->expects($this->never())->method('getUniqueHash');

        $this->observer->execute($this->observerWithQuote($quote));

        $this->assertSame('existing', $quote->getData('mp_smtp_ace_token'));
    }
}
