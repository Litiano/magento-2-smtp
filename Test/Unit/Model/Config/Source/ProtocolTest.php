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

use Mageplaza\Smtp\Model\Config\Source\Protocol;
use PHPUnit\Framework\TestCase;

/**
 * Class ProtocolTest
 * @package Mageplaza\Smtp\Test\Unit\Model\Config\Source
 */
class ProtocolTest extends TestCase
{
    /**
     * @var Protocol
     */
    private Protocol $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->model = new Protocol();
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
     * None (empty), SSL and TLS protocols must be offered, in order.
     */
    public function testToOptionArrayValues(): void
    {
        $values = array_column($this->model->toOptionArray(), 'value');

        $this->assertSame(['', 'ssl', 'tls'], $values);
    }
}
