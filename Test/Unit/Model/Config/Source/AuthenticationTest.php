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

namespace Mageplaza\Smtp\Test\Unit\Model\Config\Source;

use Mageplaza\Smtp\Model\Config\Source\Authentication;
use PHPUnit\Framework\TestCase;

/**
 * Class AuthenticationTest
 * @package Mageplaza\Smtp\Test\Unit\Model\Config\Source
 */
class AuthenticationTest extends TestCase
{
    /**
     * @var Authentication
     */
    private Authentication $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->model = new Authentication();
    }

    /**
     * Every option must expose a value and a label key.
     */
    public function testToOptionArrayFormat(): void
    {
        $result = $this->model->toOptionArray();

        $this->assertNotEmpty($result);
        foreach ($result as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
        }
    }

    /**
     * The five supported authentication methods must be present, in order.
     */
    public function testToOptionArrayValues(): void
    {
        $values = array_column($this->model->toOptionArray(), 'value');

        $this->assertSame(['smtp', 'plain', 'login', 'crammd5', 'oauth2'], $values);
    }

    /**
     * OAuth2 (Office365) must be offered, it drives the Graph API flow.
     */
    public function testOauth2OptionExists(): void
    {
        $values = array_column($this->model->toOptionArray(), 'value');

        $this->assertContains('oauth2', $values);
    }
}
