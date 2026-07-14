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

namespace Mageplaza\Smtp\Test\Unit\Ui\Component\Listing\Column;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Ui\Component\Listing\Column\CustomerName;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class CustomerNameTest
 * @package Mageplaza\Smtp\Test\Unit\Ui\Component\Listing\Column
 */
class CustomerNameTest extends TestCase
{
    /**
     * @var UrlInterface|MockObject
     */
    private UrlInterface|MockObject $urlBuilderMock;

    /**
     * @var QuoteFactory|MockObject
     */
    private QuoteFactory|MockObject $quoteFactoryMock;

    /**
     * @var EmailMarketing|MockObject
     */
    private EmailMarketing|MockObject $helperMock;

    /**
     * @var CustomerName
     */
    private CustomerName $column;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->urlBuilderMock   = $this->createMock(UrlInterface::class);
        $this->quoteFactoryMock = $this->getMockBuilder(QuoteFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $this->helperMock = $this->createMock(EmailMarketing::class);

        $this->column = (new ObjectManager($this))->getObject(CustomerName::class, [
            'urlBuilder'           => $this->urlBuilderMock,
            'quoteFactory'         => $this->quoteFactoryMock,
            'helperEmailMarketing' => $this->helperMock,
            'data'                 => ['name' => 'customer_name'],
        ]);
    }

    /**
     * Stub QuoteFactory so create()->load() yields a real Quote carrying $data.
     *
     * @param array $data
     */
    private function stubQuoteLoad(array $data): void
    {
        /** @var Quote $quote */
        $quote  = (new ObjectManager($this))->getObject(Quote::class);
        $quote->setData($data);

        $loader = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['load'])
            ->getMock();
        $loader->method('load')->willReturn($quote);
        $this->quoteFactoryMock->method('create')->willReturn($loader);
    }

    /**
     * A registered customer name is rendered as an admin edit link.
     */
    public function testPrepareDataSourceLinksRegisteredCustomer(): void
    {
        $this->stubQuoteLoad(['customer_id' => 3]);
        $this->helperMock->method('getCustomerName')->willReturn('Jane Doe');
        $this->urlBuilderMock->method('getUrl')->willReturn('http://shop/customer/index/edit/id/3');

        $dataSource = [
            'data' => ['items' => [['entity_id' => 11, 'customer_id' => 3]]],
        ];

        $result = $this->column->prepareDataSource($dataSource);
        $value  = $result['data']['items'][0]['customer_name'];

        $this->assertStringContainsString('Jane Doe', $value);
        $this->assertStringContainsString('<a href="http://shop/customer/index/edit/id/3"', $value);
    }

    /**
     * A guest customer name is rendered as plain text (no link).
     */
    public function testPrepareDataSourceRendersGuestAsPlainText(): void
    {
        $this->stubQuoteLoad([]);
        $this->helperMock->method('getCustomerName')->willReturn('Guest Buyer');
        $this->urlBuilderMock->expects($this->never())->method('getUrl');

        $dataSource = [
            'data' => ['items' => [['entity_id' => 12, 'customer_id' => null]]],
        ];

        $result = $this->column->prepareDataSource($dataSource);

        $this->assertSame('Guest Buyer', $result['data']['items'][0]['customer_name']);
    }
}
