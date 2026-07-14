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

namespace Mageplaza\Smtp\Test\Unit\Mail\Template;

use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Mail\Template\TransportBuilder as CoreTransportBuilder;
use Magento\Framework\Registry;
use Mageplaza\Smtp\Mail\Rse\Mail;
use Mageplaza\Smtp\Mail\Template\TransportBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class TransportBuilderTest
 * @package Mageplaza\Smtp\Test\Unit\Mail\Template
 */
class TransportBuilderTest extends TestCase
{
    /**
     * @var Registry|MockObject
     */
    private Registry|MockObject $registryMock;

    /**
     * @var Mail|MockObject
     */
    private Mail|MockObject $resourceMailMock;

    /**
     * @var SenderResolverInterface|MockObject
     */
    private SenderResolverInterface|MockObject $senderResolverMock;

    /**
     * @var CoreTransportBuilder|MockObject
     */
    private CoreTransportBuilder|MockObject $subjectMock;

    /**
     * @var TransportBuilder
     */
    private TransportBuilder $plugin;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->registryMock       = $this->createMock(Registry::class);
        $this->resourceMailMock   = $this->createMock(Mail::class);
        $this->senderResolverMock = $this->createMock(SenderResolverInterface::class);
        $this->subjectMock        = $this->createMock(CoreTransportBuilder::class);

        $this->plugin = new TransportBuilder(
            $this->registryMock,
            $this->resourceMailMock,
            $this->senderResolverMock
        );
    }

    /**
     * When a store is passed, the current store id is (re)registered.
     */
    public function testBeforeSetTemplateOptionsRegistersStore(): void
    {
        $this->registryMock->expects($this->once())
            ->method('unregister')->with('mp_smtp_store_id');
        $this->registryMock->expects($this->once())
            ->method('register')->with('mp_smtp_store_id', 3);

        $result = $this->plugin->beforeSetTemplateOptions($this->subjectMock, ['store' => 3]);

        $this->assertSame([['store' => 3]], $result);
    }

    /**
     * Without a store key, nothing is registered (only the stale value is cleared).
     */
    public function testBeforeSetTemplateOptionsWithoutStore(): void
    {
        $this->registryMock->expects($this->once())->method('unregister');
        $this->registryMock->expects($this->never())->method('register');

        $result = $this->plugin->beforeSetTemplateOptions($this->subjectMock, ['area' => 'frontend']);

        $this->assertSame([['area' => 'frontend']], $result);
    }

    /**
     * A string sender is resolved before being handed to the mail resource.
     */
    public function testBeforeSetFromResolvesStringSender(): void
    {
        $this->senderResolverMock->expects($this->once())
            ->method('resolve')
            ->with('general')
            ->willReturn(['email' => 'general@example.com', 'name' => 'General']);
        $this->resourceMailMock->expects($this->once())
            ->method('setFromByStore')
            ->with('general@example.com', 'General');

        $result = $this->plugin->beforeSetFrom($this->subjectMock, 'general');

        $this->assertSame(['general'], $result);
    }

    /**
     * An array sender is used as-is (no resolution needed).
     */
    public function testBeforeSetFromUsesArraySenderDirectly(): void
    {
        $from = ['email' => 'custom@example.com', 'name' => 'Custom'];
        $this->senderResolverMock->expects($this->never())->method('resolve');
        $this->resourceMailMock->expects($this->once())
            ->method('setFromByStore')
            ->with('custom@example.com', 'Custom');

        $result = $this->plugin->beforeSetFrom($this->subjectMock, $from);

        $this->assertSame([$from], $result);
    }
}
