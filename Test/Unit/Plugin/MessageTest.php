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

use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Mail\Template\TransportBuilderByStore;
use Mageplaza\Smtp\Mail\Rse\Mail;
use Mageplaza\Smtp\Plugin\Message;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class MessageTest
 * @package Mageplaza\Smtp\Test\Unit\Plugin
 */
class MessageTest extends TestCase
{
    /**
     * @var Mail|MockObject
     */
    private Mail|MockObject $resourceMailMock;

    /**
     * @var SenderResolverInterface|MockObject
     */
    private SenderResolverInterface|MockObject $senderResolverMock;

    /**
     * @var Message
     */
    private Message $plugin;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->resourceMailMock   = $this->createMock(Mail::class);
        $this->senderResolverMock = $this->createMock(SenderResolverInterface::class);
        $this->plugin             = new Message($this->resourceMailMock, $this->senderResolverMock);
    }

    /**
     * The resolved sender is forwarded to the mail resource and arguments are preserved.
     */
    public function testBeforeSetFromByStoreResolvesAndForwards(): void
    {
        $subject = $this->createMock(TransportBuilderByStore::class);
        $from    = 'general';
        $store   = 1;

        $this->senderResolverMock->expects($this->once())
            ->method('resolve')
            ->with($from, $store)
            ->willReturn(['email' => 'store@example.com', 'name' => 'Store Owner']);

        $this->resourceMailMock->expects($this->once())
            ->method('setFromByStore')
            ->with('store@example.com', 'Store Owner');

        $result = $this->plugin->beforeSetFromByStore($subject, $from, $store);

        $this->assertSame([$from, $store], $result);
    }
}
