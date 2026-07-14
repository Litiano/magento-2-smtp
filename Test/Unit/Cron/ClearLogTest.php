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

namespace Mageplaza\Smtp\Test\Unit\Cron;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Mageplaza\Smtp\Cron\ClearLog;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Model\Log;
use Mageplaza\Smtp\Model\ResourceModel\Log\Collection;
use Mageplaza\Smtp\Model\ResourceModel\Log\CollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class ClearLogTest
 * @package Mageplaza\Smtp\Test\Unit\Cron
 */
class ClearLogTest extends TestCase
{
    /**
     * @var LoggerInterface|MockObject
     */
    private LoggerInterface|MockObject $loggerMock;

    /**
     * @var DateTime|MockObject
     */
    private DateTime|MockObject $dateMock;

    /**
     * @var CollectionFactory|MockObject
     */
    private CollectionFactory|MockObject $collectionFactoryMock;

    /**
     * @var Data|MockObject
     */
    private Data|MockObject $helperMock;

    /**
     * @var ClearLog
     */
    private ClearLog $cron;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->loggerMock            = $this->createMock(LoggerInterface::class);
        $this->dateMock              = $this->createMock(DateTime::class);
        $this->collectionFactoryMock = $this->getMockBuilder(CollectionFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $this->helperMock = $this->createMock(Data::class);

        $this->cron = new ClearLog(
            $this->loggerMock,
            $this->dateMock,
            $this->collectionFactoryMock,
            $this->helperMock
        );
    }

    /**
     * When the module is disabled nothing is loaded.
     */
    public function testExecuteSkipsWhenDisabled(): void
    {
        $this->helperMock->method('isEnabled')->willReturn(false);
        $this->collectionFactoryMock->expects($this->never())->method('create');

        $this->assertSame($this->cron, $this->cron->execute());
    }

    /**
     * With a zero retention window nothing is deleted.
     */
    public function testExecuteSkipsWhenNoRetentionConfigured(): void
    {
        $this->helperMock->method('isEnabled')->willReturn(true);
        $this->helperMock->method('getConfigGeneral')->with('clean_email')->willReturn('0');
        $this->collectionFactoryMock->expects($this->never())->method('create');

        $this->cron->execute();
    }

    /**
     * Old logs are iterated page by page and deleted.
     */
    public function testExecuteDeletesOldLogs(): void
    {
        $this->helperMock->method('isEnabled')->willReturn(true);
        $this->helperMock->method('getConfigGeneral')->with('clean_email')->willReturn('30');
        $this->dateMock->method('date')->willReturn('2026-07-14 00:00:00');

        $log = $this->createMock(Log::class);
        $log->expects($this->once())->method('delete');

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getLastPageNumber')->willReturn(1);
        $collection->method('setCurPage')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$log]));

        $this->collectionFactoryMock->expects($this->once())->method('create')->willReturn($collection);

        $this->cron->execute();
    }

    /**
     * A delete failure is logged and does not abort the run.
     */
    public function testExecuteLogsDeleteException(): void
    {
        $this->helperMock->method('isEnabled')->willReturn(true);
        $this->helperMock->method('getConfigGeneral')->with('clean_email')->willReturn('30');
        $this->dateMock->method('date')->willReturn('2026-07-14 00:00:00');

        $log = $this->createMock(Log::class);
        $log->method('delete')->willThrowException(new \Exception('locked'));

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getLastPageNumber')->willReturn(1);
        $collection->method('setCurPage')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$log]));
        $this->collectionFactoryMock->method('create')->willReturn($collection);

        $this->loggerMock->expects($this->once())->method('critical');

        $this->cron->execute();
    }
}
