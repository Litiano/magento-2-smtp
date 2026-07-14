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

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Helper\GraphMailer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;

/**
 * Class GraphMailerTest
 * @package Mageplaza\Smtp\Test\Unit\Helper
 */
class GraphMailerTest extends TestCase
{
    /**
     * @var Curl|MockObject
     */
    private Curl|MockObject $curlMock;

    /**
     * @var Json|MockObject
     */
    private Json|MockObject $jsonMock;

    /**
     * @var Data|MockObject
     */
    private Data|MockObject $helperMock;

    /**
     * @var LoggerInterface|MockObject
     */
    private LoggerInterface|MockObject $loggerMock;

    /**
     * @var GraphMailer
     */
    private GraphMailer $mailer;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->curlMock   = $this->createMock(Curl::class);
        $this->jsonMock   = $this->createMock(Json::class);
        $this->helperMock = $this->createMock(Data::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        // Serialize with real JSON so we can assert the built payload.
        $this->jsonMock->method('serialize')->willReturnCallback(static fn($v) => json_encode($v));
        $this->jsonMock->method('unserialize')->willReturnCallback(static fn($v) => json_decode($v, true));

        $this->mailer = new GraphMailer(
            $this->curlMock,
            $this->jsonMock,
            $this->helperMock,
            $this->loggerMock
        );
    }

    /**
     * @return Email
     */
    private function sampleEmail(): Email
    {
        $email = new Email();
        $email->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Order confirmation')
            ->html('<p>Thank you</p>');

        return $email;
    }

    /**
     * A well-formed email is mapped to the Graph payload and posted successfully.
     */
    public function testSendEmailPostsGraphPayload(): void
    {
        $this->helperMock->method('getOauthAccessToken')->willReturn('access-token');

        $captured = null;
        $this->curlMock->method('post')->willReturnCallback(
            function ($url, $payload) use (&$captured) {
                $captured = ['url' => $url, 'payload' => $payload];
            }
        );
        $this->curlMock->method('getStatus')->willReturn(202);
        $this->curlMock->method('getBody')->willReturn('');

        $this->mailer->sendEmail($this->sampleEmail());

        // The endpoint is scoped to the (url-encoded) sender address.
        $this->assertStringContainsString('sender%40example.com', $captured['url']);
        $decoded = json_decode($captured['payload'], true);
        $this->assertSame('Order confirmation', $decoded['message']['subject']);
        $this->assertSame('HTML', $decoded['message']['body']['contentType']);
        $this->assertSame('<p>Thank you</p>', $decoded['message']['body']['content']);
        $this->assertSame(
            'recipient@example.com',
            $decoded['message']['toRecipients'][0]['emailAddress']['address']
        );
    }

    /**
     * A failure fetching the OAuth token is wrapped in a LocalizedException.
     */
    public function testSendEmailThrowsWhenOauthFails(): void
    {
        $this->helperMock->method('getOauthAccessToken')
            ->willThrowException(new \Exception('token boom'));

        $this->expectException(LocalizedException::class);
        $this->mailer->sendEmail($this->sampleEmail());
    }

    /**
     * An empty token aborts the send.
     */
    public function testSendEmailThrowsWhenTokenEmpty(): void
    {
        $this->helperMock->method('getOauthAccessToken')->willReturn('');

        $this->expectException(LocalizedException::class);
        $this->mailer->sendEmail($this->sampleEmail());
    }

    /**
     * A message without a sender cannot be sent.
     */
    public function testSendEmailThrowsWithoutSender(): void
    {
        $this->helperMock->method('getOauthAccessToken')->willReturn('access-token');

        $email = new Email();
        $email->to('recipient@example.com')->subject('Hi')->text('body');

        $this->expectException(LocalizedException::class);
        $this->mailer->sendEmail($email);
    }

    /**
     * A non-2xx Graph API response raises a LocalizedException with the API message.
     */
    public function testSendEmailThrowsOnApiError(): void
    {
        $this->helperMock->method('getOauthAccessToken')->willReturn('access-token');
        $this->curlMock->method('getStatus')->willReturn(400);
        $this->curlMock->method('getBody')->willReturn('{"error":{"message":"Bad request"}}');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Bad request');
        $this->mailer->sendEmail($this->sampleEmail());
    }
}
