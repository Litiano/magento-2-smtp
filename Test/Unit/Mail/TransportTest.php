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

namespace Mageplaza\Smtp\Test\Unit\Mail;

use Magento\Framework\Mail\Address;
use Magento\Framework\Mail\EmailMessage;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Mail\Transport;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class TransportTest
 * @package Mageplaza\Smtp\Test\Unit\Mail
 */
class TransportTest extends TestCase
{
    /**
     * @var Data|MockObject
     */
    private Data|MockObject $helperMock;

    /**
     * @var Transport|MockObject
     */
    private Transport|MockObject $transport;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->helperMock = $this->createMock(Data::class);

        // Build without the heavy plugin constructor and inject the helper only.
        $this->transport = $this->getMockBuilder(Transport::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $ref = new \ReflectionProperty(Transport::class, 'helper');
        $ref->setAccessible(true);
        $ref->setValue($this->transport, $this->helperMock);
    }

    /**
     * @param string[] $emails
     *
     * @return EmailMessage|MockObject
     */
    private function messageWithRecipients(array $emails): EmailMessage
    {
        $addresses = [];
        foreach ($emails as $email) {
            $address = $this->createMock(Address::class);
            $address->method('getEmail')->willReturn($email);
            $addresses[] = $address;
        }

        $message = $this->createMock(EmailMessage::class);
        $message->method('getTo')->willReturn($addresses);

        return $message;
    }

    /**
     * getRecipient joins all TO addresses with a comma.
     */
    public function testGetRecipientJoinsAddresses(): void
    {
        $message = $this->messageWithRecipients(['a@x.com', 'b@x.com']);

        $this->assertSame('a@x.com,b@x.com', $this->transport->getRecipient($message));
    }

    /**
     * A test email always bypasses the blacklist.
     */
    public function testValidateBlacklistSkipsTestEmail(): void
    {
        $this->helperMock->method('isTestEmail')->willReturn(true);
        $this->helperMock->expects($this->never())->method('getBlacklist');

        $message = $this->messageWithRecipients(['spam@x.com']);

        $this->assertFalse($this->transport->validateBlacklist($message));
    }

    /**
     * A recipient matching a blacklist pattern is rejected.
     */
    public function testValidateBlacklistMatchesPattern(): void
    {
        $this->helperMock->method('isTestEmail')->willReturn(false);
        $this->helperMock->method('getBlacklist')->willReturn('/@spam\.com/');

        $message = $this->messageWithRecipients(['user@spam.com']);

        $this->assertTrue($this->transport->validateBlacklist($message));
    }

    /**
     * A recipient not matching any pattern passes.
     */
    public function testValidateBlacklistAllowsUnlistedRecipient(): void
    {
        $this->helperMock->method('isTestEmail')->willReturn(false);
        $this->helperMock->method('getBlacklist')->willReturn('/@spam\.com/');

        $message = $this->messageWithRecipients(['user@good.com']);

        $this->assertFalse($this->transport->validateBlacklist($message));
    }

    /**
     * An empty blacklist lets every recipient through.
     */
    public function testValidateBlacklistEmptyList(): void
    {
        $this->helperMock->method('isTestEmail')->willReturn(false);
        $this->helperMock->method('getBlacklist')->willReturn('');

        $message = $this->messageWithRecipients(['user@good.com']);

        $this->assertFalse($this->transport->validateBlacklist($message));
    }

    /**
     * A malformed pattern is ignored rather than aborting validation.
     */
    public function testValidateBlacklistIgnoresInvalidPattern(): void
    {
        $this->helperMock->method('isTestEmail')->willReturn(false);
        // Missing delimiters -> preg_match emits a warning/throws, which is caught.
        $this->helperMock->method('getBlacklist')->willReturn('not-a-valid-regex');

        $message = $this->messageWithRecipients(['user@good.com']);

        $this->assertFalse($this->transport->validateBlacklist($message));
    }
}
