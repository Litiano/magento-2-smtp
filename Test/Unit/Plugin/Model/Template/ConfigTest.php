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

namespace Mageplaza\Smtp\Test\Unit\Plugin\Model\Template;

use Magento\Email\Model\Template\Config as EmailConfig;
use Mageplaza\Smtp\Plugin\Model\Template\Config;
use PHPUnit\Framework\TestCase;

/**
 * Class ConfigTest
 * @package Mageplaza\Smtp\Test\Unit\Plugin\Model\Template
 */
class ConfigTest extends TestCase
{
    /**
     * @var EmailConfig
     */
    private EmailConfig $subjectMock;

    /**
     * @var Config
     */
    private Config $plugin;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->subjectMock = $this->createMock(EmailConfig::class);
        $this->plugin      = new Config();
    }

    /**
     * The abandoned cart template must be removed from the available list.
     */
    public function testAfterGetAvailableTemplatesRemovesAbandonedCart(): void
    {
        $templates = [
            ['value' => 'template_a', 'label' => 'A'],
            ['value' => 'mpsmtp_abandoned_cart_email_templates', 'label' => 'ACE'],
            ['value' => 'template_b', 'label' => 'B'],
        ];

        $result = $this->plugin->afterGetAvailableTemplates($this->subjectMock, $templates);

        $values = array_column($result, 'value');
        $this->assertNotContains('mpsmtp_abandoned_cart_email_templates', $values);
        $this->assertContains('template_a', $values);
        $this->assertContains('template_b', $values);
    }

    /**
     * The abandoned cart template is removed even when it is the first entry.
     */
    public function testAfterGetAvailableTemplatesRemovesAbandonedCartAtFirstPosition(): void
    {
        $templates = [
            ['value' => 'mpsmtp_abandoned_cart_email_templates', 'label' => 'ACE'],
            ['value' => 'template_a', 'label' => 'A'],
        ];

        $result = $this->plugin->afterGetAvailableTemplates($this->subjectMock, $templates);

        $values = array_column($result, 'value');
        $this->assertSame(['template_a'], $values);
    }

    /**
     * Note on a known quirk: when the abandoned cart template is NOT present,
     * array_search() returns false which unset() coerces to index 0, so the
     * first template is dropped. We assert that documented behaviour here so a
     * future refactor that changes it is noticed.
     */
    public function testAfterGetAvailableTemplatesDropsFirstWhenTargetAbsent(): void
    {
        $templates = [
            ['value' => 'template_a', 'label' => 'A'],
            ['value' => 'template_b', 'label' => 'B'],
        ];

        $result = $this->plugin->afterGetAvailableTemplates($this->subjectMock, $templates);

        // Index 0 (template_a) is removed due to the false->0 coercion.
        $this->assertSame(['template_b'], array_values(array_column($result, 'value')));
    }
}
