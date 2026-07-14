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

namespace Mageplaza\Smtp\Test\Unit\Helper;

use Magento\Bundle\Helper\Catalog\Product\Configuration as BundleConfiguration;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Helper\Product\Configuration as CatalogConfiguration;
use Magento\Framework\Escaper;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Mageplaza\Smtp\Helper\EmailMarketing;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class EmailMarketingTest
 * @package Mageplaza\Smtp\Test\Unit\Helper
 */
class EmailMarketingTest extends TestCase
{
    /**
     * @var EmailMarketing|MockObject
     */
    private EmailMarketing|MockObject $helper;

    /**
     * @var ObjectManager
     */
    private ObjectManager $objectManager;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        // The constructor has 39 dependencies; skip it and inject only what we test.
        $this->helper = $this->getMockBuilder(EmailMarketing::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $this->objectManager = new ObjectManager($this);
    }

    /**
     * @param string $property
     * @param mixed  $value
     */
    private function setProperty(string $property, $value): void
    {
        $ref = new \ReflectionProperty(EmailMarketing::class, $property);
        $ref->setAccessible(true);
        $ref->setValue($this->helper, $value);
    }

    /**
     * splitSku delegates to the catalog helper.
     */
    public function testSplitSkuDelegates(): void
    {
        $catalogHelper = $this->createMock(CatalogHelper::class);
        $catalogHelper->expects($this->once())
            ->method('splitSku')
            ->with('ABC-123')
            ->willReturn(['ABC', '123']);
        $this->setProperty('catalogHelper', $catalogHelper);

        $this->assertSame(['ABC', '123'], $this->helper->splitSku('ABC-123'));
    }

    /**
     * formatDate converts config time to UTC then formats it.
     */
    public function testFormatDateConvertsAndFormats(): void
    {
        $localeDate = $this->createMock(TimezoneInterface::class);
        $localeDate->expects($this->once())
            ->method('convertConfigTimeToUtc')
            ->with('2026-07-14 10:00:00')
            ->willReturn('2026-07-14 03:00:00');
        $localeDate->expects($this->once())
            ->method('formatDateTime')
            ->willReturn('Jul 14, 2026, 3:00:00 AM');
        $this->setProperty('_localeDate', $localeDate);

        $this->assertSame(
            'Jul 14, 2026, 3:00:00 AM',
            $this->helper->formatDate('2026-07-14 10:00:00')
        );
    }

    /**
     * A bundle item uses the bundle configuration provider.
     */
    public function testGetProductOptionsForBundle(): void
    {
        $bundleConfig  = $this->createMock(BundleConfiguration::class);
        $catalogConfig = $this->createMock(CatalogConfiguration::class);
        $this->setProperty('bundleProductConfiguration', $bundleConfig);
        $this->setProperty('productConfig', $catalogConfig);

        /** @var Item $item */
        $item = $this->objectManager->getObject(Item::class);
        $item->setData('product_type', 'bundle');

        $bundleConfig->expects($this->once())->method('getOptions')->with($item)->willReturn(['b']);
        $catalogConfig->expects($this->never())->method('getOptions');

        $this->assertSame(['b'], $this->helper->getProductOptions($item));
    }

    /**
     * A non-bundle item uses the default catalog configuration provider.
     */
    public function testGetProductOptionsForSimple(): void
    {
        $bundleConfig  = $this->createMock(BundleConfiguration::class);
        $catalogConfig = $this->createMock(CatalogConfiguration::class);
        $this->setProperty('bundleProductConfiguration', $bundleConfig);
        $this->setProperty('productConfig', $catalogConfig);

        /** @var Item $item */
        $item = $this->objectManager->getObject(Item::class);
        $item->setData('product_type', 'simple');

        $catalogConfig->expects($this->once())->method('getOptions')->with($item)->willReturn(['s']);
        $bundleConfig->expects($this->never())->method('getOptions');

        $this->assertSame(['s'], $this->helper->getProductOptions($item));
    }

    /**
     * A customer name is built from the quote's first and last name.
     */
    public function testGetCustomerNameFromQuoteNames(): void
    {
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnArgument(0);
        $this->setProperty('escaper', $escaper);

        /** @var Quote $quote */
        $quote = $this->objectManager->getObject(Quote::class);
        $quote->setData('customer_firstname', 'John');
        $quote->setData('customer_lastname', 'Doe');

        $this->assertSame('John Doe', $this->helper->getCustomerName($quote));
    }

    /**
     * With no name and no customer, the email local-part is used.
     */
    public function testGetCustomerNameFallsBackToEmail(): void
    {
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnArgument(0);
        $this->setProperty('escaper', $escaper);

        /** @var Quote $quote */
        $quote = $this->objectManager->getObject(Quote::class);
        $quote->setData('customer_email', 'guest.buyer@example.com');

        $this->assertSame('guest.buyer', $this->helper->getCustomerName($quote));
    }
}
