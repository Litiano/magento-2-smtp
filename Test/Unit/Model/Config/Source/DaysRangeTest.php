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

use Mageplaza\Smtp\Model\Config\Source\DaysRange;
use PHPUnit\Framework\TestCase;

/**
 * Class DaysRangeTest
 * @package Mageplaza\Smtp\Test\Unit\Model\Config\Source
 */
class DaysRangeTest extends TestCase
{
    /**
     * @var DaysRange
     */
    private DaysRange $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->model = new DaysRange();
    }

    /**
     * toArray() must be keyed by the range identifiers.
     */
    public function testToArrayKeys(): void
    {
        $keys = array_keys($this->model->toArray());

        // PHP casts numeric string array keys ('90') to integers.
        $this->assertSame(['lifetime', 90, 365, 730, DaysRange::CUSTOM], $keys);
    }

    /**
     * toOptionArray() must reshape every toArray() entry into label/value pairs
     * while preserving the source keys as values.
     */
    public function testToOptionArrayMirrorsToArray(): void
    {
        $array   = $this->model->toArray();
        $options = $this->model->toOptionArray();

        $this->assertSameSize($array, $options);

        foreach ($options as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
            // The value must be a key of toArray() and the label its matching entry.
            // __() returns a fresh Phrase each call, so compare the rendered strings.
            $this->assertArrayHasKey($option['value'], $array);
            $this->assertSame((string) $array[$option['value']], (string) $option['label']);
        }
    }

    /**
     * The custom range option drives the date-range picker.
     */
    public function testCustomOptionExists(): void
    {
        $values = array_column($this->model->toOptionArray(), 'value');

        $this->assertContains(DaysRange::CUSTOM, $values);
    }
}
