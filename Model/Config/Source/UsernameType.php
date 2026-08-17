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

namespace Mageplaza\Smtp\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class UsernameType
 * @package Mageplaza\Smtp\Model\Config\Source
 */
class UsernameType implements ArrayInterface
{
    const TYPE_EMAIL   = 'email';
    const TYPE_API_KEY = 'api_key';

    /**
     * to option array
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => self::TYPE_EMAIL,
                'label' => __('Email Address')
            ],
            [
                'value' => self::TYPE_API_KEY,
                'label' => __('API Key')
            ],
        ];

        return $options;
    }
}
