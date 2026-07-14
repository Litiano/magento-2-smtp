<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the mageplaza.com license that is
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

namespace Mageplaza\Smtp\Test\Unit\Ui\Component\Listing\Column;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\UrlInterface;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Ui\Component\Listing\Column\Actions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class ActionsTest
 * @package Mageplaza\Smtp\Test\Unit\Ui\Component\Listing\Column
 */
class ActionsTest extends TestCase
{
    /**
     * @var UrlInterface|MockObject
     */
    private UrlInterface|MockObject $urlBuilderMock;

    /**
     * @var Actions
     */
    private Actions $column;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->urlBuilderMock = $this->createMock(UrlInterface::class);

        $this->column = (new ObjectManager($this))->getObject(Actions::class, [
            'urlBuilder' => $this->urlBuilderMock,
            'helperData' => $this->createMock(Data::class),
            'data'       => ['name' => 'actions'],
        ]);
    }

    /**
     * Each row receives view/resend/delete actions built from the row id.
     */
    public function testPrepareDataSourceAddsActions(): void
    {
        $this->urlBuilderMock->method('getUrl')->willReturnCallback(
            static fn($route, $params) => 'http://shop/' . $route . '/id/' . $params['id']
        );

        $dataSource = [
            'data' => [
                'items' => [
                    ['id' => 7, 'subject' => 'Order confirmation'],
                ],
            ],
        ];

        $result  = $this->column->prepareDataSource($dataSource);
        $actions = $result['data']['items'][0]['actions'];

        $this->assertArrayHasKey('view', $actions);
        $this->assertArrayHasKey('resend', $actions);
        $this->assertArrayHasKey('delete', $actions);
        $this->assertSame('http://shop/adminhtml/smtp/email/id/7', $actions['resend']['href']);
        $this->assertSame('http://shop/adminhtml/smtp/delete/id/7', $actions['delete']['href']);
    }

    /**
     * A data source without items is passed through untouched.
     */
    public function testPrepareDataSourceWithoutItems(): void
    {
        $dataSource = ['data' => []];

        $this->assertSame($dataSource, $this->column->prepareDataSource($dataSource));
    }
}
