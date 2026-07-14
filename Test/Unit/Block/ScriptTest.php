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

namespace Mageplaza\Smtp\Test\Unit\Block;

use Magento\Catalog\Helper\Data as TaxHelper;
use Magento\Framework\App\Request\Http;
use Magento\Framework\DataObject;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Smtp\Block\Script;
use Mageplaza\Smtp\Helper\EmailMarketing;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class ScriptTest
 * @package Mageplaza\Smtp\Test\Unit\Block
 */
class ScriptTest extends TestCase
{
    /**
     * Build a Script block without invoking the heavy block constructor.
     *
     * @param array $mockedMethods
     *
     * @return Script|MockObject
     */
    private function createBlock(array $mockedMethods = []): Script
    {
        return $this->getMockBuilder(Script::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    /**
     * @param object $object
     * @param string $property
     * @param mixed  $value
     */
    private function setProperty(object $object, string $property, $value): void
    {
        $ref = new \ReflectionProperty(Script::class, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }

    /**
     * getIdentities exposes the module cache tag for FPC invalidation.
     */
    public function testGetIdentities(): void
    {
        $this->assertSame([EmailMarketing::CACHE_TAG], $this->createBlock()->getIdentities());
    }

    /**
     * The checkout success and thank-you pages are recognised.
     */
    public function testIsSuccessPageTrue(): void
    {
        $request = $this->createMock(Http::class);
        $request->method('getFullActionName')->willReturn('checkout_onepage_success');

        $block = $this->createBlock(['getRequest']);
        $block->method('getRequest')->willReturn($request);

        $this->assertTrue($block->isSuccessPage());
    }

    /**
     * Other pages are not treated as success pages.
     */
    public function testIsSuccessPageFalse(): void
    {
        $request = $this->createMock(Http::class);
        $request->method('getFullActionName')->willReturn('catalog_product_view');

        $block = $this->createBlock(['getRequest']);
        $block->method('getRequest')->willReturn($request);

        $this->assertFalse($block->isSuccessPage());
    }

    /**
     * getCurrencyCode reads the current store currency.
     */
    public function testGetCurrencyCode(): void
    {
        $currency = new DataObject(); // getCode() is magic on DataObject
        $currency->setData('code', 'USD');
        $store = $this->createMock(Store::class);
        $store->method('getCurrentCurrency')->willReturn($currency);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $block = $this->createBlock();
        $this->setProperty($block, '_storeManager', $storeManager);

        $this->assertSame('USD', $block->getCurrencyCode());
    }

    /**
     * A simple product price is converted to the presentation currency.
     */
    public function testGetPriceSimpleProduct(): void
    {
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->method('convert')->with(100.0)->willReturn(120.0);

        $block = $this->createBlock();
        $this->setProperty($block, 'priceCurrency', $priceCurrency);

        $product = new DataObject(['final_price' => 100.0, 'type_id' => 'simple']);

        $this->assertSame('120.00', $block->getPrice($product));
    }

    /**
     * A configurable product price is taken as-is (no currency conversion).
     */
    public function testGetPriceConfigurableProduct(): void
    {
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->method('convert')->willReturn(999.0); // overwritten for configurable

        $block = $this->createBlock();
        $this->setProperty($block, 'priceCurrency', $priceCurrency);

        $product = new DataObject(['final_price' => 50.0, 'type_id' => 'configurable']);

        $this->assertSame('50.00', $block->getPrice($product));
    }

    /**
     * Including tax routes the price through the tax helper.
     */
    public function testGetPriceWithTax(): void
    {
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->method('convert')->willReturnArgument(0);

        $taxHelper = $this->createMock(TaxHelper::class);
        $taxHelper->method('getTaxPrice')->willReturn(110.0);

        $block = $this->createBlock();
        $this->setProperty($block, 'priceCurrency', $priceCurrency);
        $this->setProperty($block, 'taxHelper', $taxHelper);

        $product = new DataObject(['final_price' => 100.0, 'type_id' => 'simple']);

        $this->assertSame('110.00', $block->getPrice($product, true));
    }
}
