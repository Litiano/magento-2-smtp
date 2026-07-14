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

use Magento\Framework\Event\Observer;
use Magento\PageCache\Model\Cache\Type;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Observer\Customer\LoginSuccess;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class LoginSuccessTest
 * @package Mageplaza\Smtp\Test\Unit\Observer\Customer
 */
class LoginSuccessTest extends TestCase
{
    /**
     * @var Type|MockObject
     */
    private Type|MockObject $fullPageCacheMock;

    /**
     * @var Data|MockObject
     */
    private Data|MockObject $helperDataMock;

    /**
     * @var EmailMarketing|MockObject
     */
    private EmailMarketing|MockObject $helperMarketingMock;

    /**
     * @var LoginSuccess
     */
    private LoginSuccess $observer;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->fullPageCacheMock   = $this->createMock(Type::class);
        $this->helperDataMock      = $this->createMock(Data::class);
        $this->helperMarketingMock = $this->createMock(EmailMarketing::class);

        $this->observer = new LoginSuccess(
            $this->fullPageCacheMock,
            $this->helperDataMock,
            $this->helperMarketingMock
        );
    }

    /**
     * When email marketing is enabled the full page cache is cleaned by tag.
     */
    public function testExecuteCleansCacheWhenEnabled(): void
    {
        $this->helperDataMock->method('getScopeId')->willReturn(1);
        $this->helperMarketingMock->method('isEnableEmailMarketing')->with(1)->willReturn(true);

        $this->fullPageCacheMock->expects($this->once())
            ->method('clean')
            ->with($this->anything(), [EmailMarketing::CACHE_TAG]);

        $this->observer->execute($this->createMock(Observer::class));
    }

    /**
     * When email marketing is disabled the cache is left untouched.
     */
    public function testExecuteSkipsCacheWhenDisabled(): void
    {
        $this->helperDataMock->method('getScopeId')->willReturn(1);
        $this->helperMarketingMock->method('isEnableEmailMarketing')->willReturn(false);

        $this->fullPageCacheMock->expects($this->never())->method('clean');

        $this->observer->execute($this->createMock(Observer::class));
    }

    /**
     * A failure resolving the scope id is swallowed and null scope is used.
     */
    public function testExecuteHandlesScopeIdException(): void
    {
        $this->helperDataMock->method('getScopeId')
            ->willThrowException(new \Exception('no scope'));
        $this->helperMarketingMock->expects($this->once())
            ->method('isEnableEmailMarketing')
            ->with(null)
            ->willReturn(false);

        $this->fullPageCacheMock->expects($this->never())->method('clean');

        $this->observer->execute($this->createMock(Observer::class));
    }
}
