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

namespace Mageplaza\Smtp\Test\Unit\Model;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Model\CheckoutManagement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class CheckoutManagementTest
 * @package Mageplaza\Smtp\Test\Unit\Model
 */
class CheckoutManagementTest extends TestCase
{
    /**
     * @var QuoteIdMaskFactory|MockObject
     */
    private QuoteIdMaskFactory|MockObject $quoteIdMaskFactoryMock;

    /**
     * @var CartRepositoryInterface|MockObject
     */
    private CartRepositoryInterface|MockObject $cartRepositoryMock;

    /**
     * @var EmailMarketing|MockObject
     */
    private EmailMarketing|MockObject $helperMock;

    /**
     * @var CheckoutManagement
     */
    private CheckoutManagement $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->quoteIdMaskFactoryMock = $this->getMockBuilder(QuoteIdMaskFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $this->cartRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->helperMock         = $this->createMock(EmailMarketing::class);

        $this->model = new CheckoutManagement(
            $this->quoteIdMaskFactoryMock,
            $this->cartRepositoryMock,
            $this->helperMock
        );

        // EmailMarketing::jsonDecode() resolves the Json helper through the global OM.
        $jsonHelper = $this->createMock(JsonHelper::class);
        $jsonHelper->method('jsonDecode')->willReturnCallback(static fn($v) => json_decode($v, true));
        $om = $this->createMock(ObjectManagerInterface::class);
        $om->method('get')->willReturn($jsonHelper);
        ObjectManager::setInstance($om);
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
     * With the feature off, no request is built or sent.
     */
    public function testUpdateOrderSkipsWhenDisabled(): void
    {
        $this->helperMock->method('isEnableEmailMarketing')->willReturn(false);
        $this->quoteIdMaskFactoryMock->expects($this->never())->method('create');
        $this->helperMock->expects($this->never())->method('sendRequestWithoutWaitResponse');

        $this->model->updateOrder('masked123', '{"city":"NY"}', false);
    }

    /**
     * The masked cart is resolved, the address decoded and the ACE payload pushed.
     */
    public function testUpdateOrderSendsAceData(): void
    {
        $this->enableGate();

        // load() returns a DataObject whose magic getQuoteId() resolves the value.
        $quoteIdMask = $this->getMockBuilder(QuoteIdMask::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['load'])
            ->getMock();
        $quoteIdMask->expects($this->once())->method('load')->with('masked123', 'masked_id')
            ->willReturn(new DataObject(['quote_id' => 99]));
        $this->quoteIdMaskFactoryMock->method('create')->willReturn($quoteIdMask);

        $quote = new DataObject(['entity_id' => 99]);
        $this->cartRepositoryMock->expects($this->once())->method('getActive')->with(99)->willReturn($quote);

        $this->helperMock->expects($this->once())
            ->method('getACEData')
            ->with($quote, ['city' => 'NY'], false)
            ->willReturn(['ace' => 'payload']);

        $this->helperMock->expects($this->once())
            ->method('sendRequestWithoutWaitResponse')
            ->with(['ace' => 'payload'], EmailMarketing::CHECKOUT_URL);

        $this->model->updateOrder('masked123', '{"city":"NY"}', false);
    }
}
