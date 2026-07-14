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

namespace Mageplaza\Smtp\Test\Unit\Plugin\Config;

use Magento\Config\Model\Config;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Mageplaza\Smtp\Plugin\Config\ConfigPlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class ConfigPluginTest
 * @package Mageplaza\Smtp\Test\Unit\Plugin\Config
 */
class ConfigPluginTest extends TestCase
{
    /**
     * @var RequestInterface|MockObject
     */
    private RequestInterface|MockObject $requestMock;

    /**
     * @var ManagerInterface|MockObject
     */
    private ManagerInterface|MockObject $messageManagerMock;

    /**
     * @var ConfigPlugin
     */
    private ConfigPlugin $plugin;

    /**
     * @var ObjectManager
     */
    private ObjectManager $objectManager;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->requestMock        = $this->createMock(RequestInterface::class);
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->plugin             = new ConfigPlugin($this->requestMock, $this->messageManagerMock);
        $this->objectManager      = new ObjectManager($this);
    }

    /**
     * Build a real Config object carrying section + groups data.
     *
     * @param string $section
     * @param array  $groups
     *
     * @return Config
     */
    private function createConfig(string $section, array $groups): Config
    {
        /** @var Config $config */
        $config = $this->objectManager->getObject(Config::class);
        $config->setData('section', $section);
        $config->setData('groups', $groups);

        return $config;
    }

    /**
     * @param string $protocol
     * @param string $port
     *
     * @return array
     */
    private function smtpGroups(string $protocol, string $port): array
    {
        return [
            'configuration_option' => [
                'fields' => [
                    'protocol' => ['value' => $protocol],
                    'port'     => ['value' => $port],
                ],
            ],
        ];
    }

    /**
     * TLS with the SSL-only port 465 is silently corrected to 587 with a notice.
     */
    public function testBeforeSaveFixesTlsPort(): void
    {
        $config = $this->createConfig('smtp', $this->smtpGroups('tls', '465'));
        $this->messageManagerMock->expects($this->once())->method('addNoticeMessage');

        $result = $this->plugin->beforeSave($config);

        $groups = $config->getGroups();
        $this->assertSame('587', $groups['configuration_option']['fields']['port']['value']);
        $this->assertSame([], $result);
    }

    /**
     * SSL keeps port 465 untouched and raises no notice.
     */
    public function testBeforeSaveKeepsSslPort(): void
    {
        $config = $this->createConfig('smtp', $this->smtpGroups('ssl', '465'));
        $this->messageManagerMock->expects($this->never())->method('addNoticeMessage');

        $this->plugin->beforeSave($config);

        $groups = $config->getGroups();
        $this->assertSame('465', $groups['configuration_option']['fields']['port']['value']);
    }

    /**
     * A non-SMTP section is ignored entirely.
     */
    public function testBeforeSaveIgnoresOtherSections(): void
    {
        $config = $this->createConfig('general', $this->smtpGroups('tls', '465'));
        $this->messageManagerMock->expects($this->never())->method('addNoticeMessage');

        $result = $this->plugin->beforeSave($config);

        $groups = $config->getGroups();
        $this->assertSame('465', $groups['configuration_option']['fields']['port']['value']);
        $this->assertSame([], $result);
    }
}
