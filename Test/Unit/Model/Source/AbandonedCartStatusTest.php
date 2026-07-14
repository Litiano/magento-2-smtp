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

namespace Mageplaza\Smtp\Test\Unit\Model\Source;

use Mageplaza\Smtp\Model\Source\AbandonedCartStatus;
use PHPUnit\Framework\TestCase;

/**
 * Class AbandonedCartStatusTest
 * @package Mageplaza\Smtp\Test\Unit\Model\Source
 */
class AbandonedCartStatusTest extends TestCase
{
    /**
     * @var AbandonedCartStatus
     */
    private AbandonedCartStatus $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->model = new AbandonedCartStatus();
    }

    /**
     * Sent / Wait-for-send constants.
     */
    public function testConstants(): void
    {
        $this->assertSame(1, AbandonedCartStatus::SENT);
        $this->assertSame(0, AbandonedCartStatus::WAIT_FOR_SEND);
    }

    /**
     * The static option array is keyed by status id.
     */
    public function testGetOptionArrayKeys(): void
    {
        $options = AbandonedCartStatus::getOptionArray();

        $this->assertArrayHasKey(AbandonedCartStatus::SENT, $options);
        $this->assertArrayHasKey(AbandonedCartStatus::WAIT_FOR_SEND, $options);
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
     * toOptionArray() must reshape getOptionArray() preserving the status ids
     * as values and their labels.
     */
    public function testToOptionArrayMirrorsGetOptionArray(): void
    {
        $source  = AbandonedCartStatus::getOptionArray();
        $options = $this->model->toOptionArray();

        $this->assertSameSize($source, $options);

        foreach ($options as $option) {
            // __() returns a fresh Phrase each call, so compare the rendered strings.
            $this->assertArrayHasKey($option['value'], $source);
            $this->assertSame((string) $source[$option['value']], (string) $option['label']);
        }
    }
}
