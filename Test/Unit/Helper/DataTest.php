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

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Mageplaza\Smtp\Helper\Data;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class DataTest
 * @package Mageplaza\Smtp\Test\Unit\Helper
 */
class DataTest extends TestCase
{
    /**
     * Build a Data helper without invoking its heavy constructor.
     *
     * @param array $mockedMethods
     *
     * @return Data|MockObject
     */
    private function createHelper(array $mockedMethods = []): Data
    {
        return $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    /**
     * Inject a protected/inherited property value via reflection.
     *
     * @param object $object
     * @param string $property
     * @param mixed  $value
     */
    private function setProperty(object $object, string $property, $value): void
    {
        $ref = new \ReflectionProperty(Data::class, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }

    /**
     * OAuth2 authentication selects the Graph API path.
     */
    public function testShouldUseGraphApiDetectsOauth2(): void
    {
        $helper = $this->createHelper();

        $this->assertTrue($helper->shouldUseGraphApi(null, ['authentication' => 'oauth2']));
        $this->assertTrue($helper->shouldUseGraphApi(null, ['auth' => 'oauth2']));
        $this->assertFalse($helper->shouldUseGraphApi(null, ['authentication' => 'login']));
        $this->assertFalse($helper->shouldUseGraphApi(null, ['authentication' => '']));
    }

    /**
     * A non-OAuth2 configuration yields no access token.
     */
    public function testGetOauthAccessTokenReturnsNullWhenNotOauth2(): void
    {
        $helper = $this->createHelper();

        $this->assertNull($helper->getOauthAccessToken(1, ['authentication' => 'plain']));
    }

    /**
     * Missing OAuth2 credentials raise a descriptive exception.
     */
    public function testGetOauthAccessTokenThrowsOnMissingCredentials(): void
    {
        $helper = $this->createHelper();

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Tenant ID');

        // secret present (no ':' so no decrypt) but tenant + client id missing.
        $helper->getOauthAccessToken(1, [
            'authentication'      => 'oauth2',
            'oauth_client_secret' => 'plain-secret',
        ]);
    }

    /**
     * A cached token short-circuits the HTTP round trip.
     */
    public function testGetOauthAccessTokenReturnsCachedToken(): void
    {
        $helper = $this->createHelper();

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn('cached-token');
        $this->setProperty($helper, 'cache', $cache);

        $token = $helper->getOauthAccessToken(1, [
            'authentication'      => 'oauth2',
            'oauth_tenant_id'     => 'tenant',
            'oauth_client_id'     => 'client',
            'oauth_client_secret' => 'plain-secret',
        ]);

        $this->assertSame('cached-token', $token);
    }

    /**
     * getSmtpConfig builds the "<group>/<code>" path and delegates.
     */
    public function testGetSmtpConfigBuildsPath(): void
    {
        $helper = $this->createHelper(['getModuleConfig']);
        $helper->expects($this->once())
            ->method('getModuleConfig')
            ->with('configuration_option/password', null)
            ->willReturn('encrypted');

        $this->assertSame('encrypted', $helper->getSmtpConfig('password'));
    }

    /**
     * getDeveloperConfig builds the developer group path and delegates.
     */
    public function testGetDeveloperConfigBuildsPath(): void
    {
        $helper = $this->createHelper(['getModuleConfig']);
        $helper->expects($this->once())
            ->method('getModuleConfig')
            ->with('developer/debug', 5)
            ->willReturn('1');

        $this->assertSame('1', $helper->getDeveloperConfig('debug', 5));
    }

    /**
     * isTestEmail only matches the admin test-email action.
     */
    public function testIsTestEmail(): void
    {
        $request = $this->createMock(Http::class);
        $request->method('getFullActionName')->willReturn('adminhtml_smtp_test');

        $helper = $this->createHelper();
        $this->setProperty($helper, '_request', $request);

        $this->assertTrue($helper->isTestEmail());
    }

    /**
     * A non-test action is not treated as a test email.
     */
    public function testIsTestEmailFalseForOtherActions(): void
    {
        $request = $this->createMock(Http::class);
        $request->method('getFullActionName')->willReturn('checkout_index_index');

        $helper = $this->createHelper();
        $this->setProperty($helper, '_request', $request);

        $this->assertFalse($helper->isTestEmail());
    }
}
