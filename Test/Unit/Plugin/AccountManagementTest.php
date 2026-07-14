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

namespace Mageplaza\Smtp\Test\Unit\Plugin;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\AccountManagement as CustomerAccountManagement;
use Magento\Framework\DataObject;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Plugin\AccountManagement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class AccountManagementTest
 * @package Mageplaza\Smtp\Test\Unit\Plugin
 */
class AccountManagementTest extends TestCase
{
    /**
     * @var CheckoutSession|MockObject
     */
    private CheckoutSession|MockObject $checkoutSessionMock;

    /**
     * @var CartRepositoryInterface|MockObject
     */
    private CartRepositoryInterface|MockObject $cartRepositoryMock;

    /**
     * @var EmailMarketing|MockObject
     */
    private EmailMarketing|MockObject $helperMock;

    /**
     * @var CustomerAccountManagement|MockObject
     */
    private CustomerAccountManagement|MockObject $subjectMock;

    /**
     * @var AccountManagement
     */
    private AccountManagement $plugin;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->checkoutSessionMock = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getQuote'])
            ->getMock();
        $this->cartRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->helperMock         = $this->createMock(EmailMarketing::class);
        $this->subjectMock        = $this->createMock(CustomerAccountManagement::class);

        $this->plugin = new AccountManagement(
            $this->checkoutSessionMock,
            $this->cartRepositoryMock,
            $this->helperMock
        );
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
     * The original result is always returned unchanged.
     */
    public function testAfterIsEmailAvailableReturnsResultWhenDisabled(): void
    {
        $this->helperMock->method('isEnableEmailMarketing')->willReturn(false);
        $this->cartRepositoryMock->expects($this->never())->method('get');

        $result = $this->plugin->afterIsEmailAvailable($this->subjectMock, true, 'a@b.com');

        $this->assertTrue($result);
    }

    /**
     * With an active cart, the customer email is stored on the quote and saved.
     */
    public function testAfterIsEmailAvailableUpdatesQuoteEmail(): void
    {
        $this->enableGate();

        $sessionQuote = new DataObject(['id' => 42]);
        $this->checkoutSessionMock->method('getQuote')->willReturn($sessionQuote);

        // A real Quote (instanceof CartInterface) so save()'s type hint is satisfied.
        /** @var Quote $quote */
        $quote = (new ObjectManager($this))->getObject(Quote::class);
        $this->cartRepositoryMock->expects($this->once())->method('get')->with(42)->willReturn($quote);
        $this->cartRepositoryMock->expects($this->once())->method('save')->with($quote);

        $result = $this->plugin->afterIsEmailAvailable($this->subjectMock, false, 'buyer@example.com');

        $this->assertFalse($result);
        $this->assertSame('buyer@example.com', $quote->getData('customer_email'));
    }

    /**
     * Without an active cart id, nothing is loaded or saved.
     */
    public function testAfterIsEmailAvailableSkipsWithoutCartId(): void
    {
        $this->enableGate();
        $this->checkoutSessionMock->method('getQuote')->willReturn(new DataObject());
        $this->cartRepositoryMock->expects($this->never())->method('get');

        $result = $this->plugin->afterIsEmailAvailable($this->subjectMock, true, 'a@b.com');

        $this->assertTrue($result);
    }
}
