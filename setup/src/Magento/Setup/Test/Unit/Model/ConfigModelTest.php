<?php declare(strict_types=1);
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Setup\Test\Unit\Model;

use Magento\Backend\Setup\ConfigOptionsList;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\DeploymentConfig\Writer;
use Magento\Framework\Config\Data\ConfigData;
use Magento\Framework\Config\File\ConfigFilePool;
use Magento\Framework\Setup\FilePermissions;
use Magento\Framework\Setup\Option\TextConfigOption;
use Magento\Setup\Model\ConfigModel;
use Magento\Setup\Model\ConfigOptionsListCollector;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigModelTest extends TestCase
{
    /**
     * @var MockObject|ConfigModel
     */
    private $configModel;

    /**
     * @var MockObject|ConfigOptionsListCollector
     */
    private $collector;

    /**
     * @var MockObject|Writer
     */
    private $writer;

    /**
     * @var MockObject|DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @var MockObject|ConfigData
     */
    private $configData;

    /**
     * @var MockObject|FilePermissions
     */
    private $filePermissions;

    /**
     * @var MockObject|ConfigOptionsList
     */
    private $configOptionsList;

    public function setUp(): void
    {
        $this->collector = $this->createMock(ConfigOptionsListCollector::class);
        $this->writer = $this->createMock(Writer::class);
        $this->deploymentConfig = $this->createMock(DeploymentConfig::class);
        $this->configOptionsList = $this->createMock(ConfigOptionsList::class);
        $this->configData = $this->createMock(ConfigData::class);
        $this->filePermissions = $this->createMock(FilePermissions::class);

        $this->deploymentConfig->expects($this->any())->method('get');

        $this->configModel = new ConfigModel(
            $this->collector,
            $this->writer,
            $this->deploymentConfig,
            $this->filePermissions
        );
    }

    public function testValidate()
    {
        $option = $this->createMock(TextConfigOption::class);
        $option->expects($this->exactly(3))->method('getName')->willReturn('Fake');
        $optionsSet = [
            $option,
            $option,
            $option
        ];
        $configOption = $this->configOptionsList;
        $configOption->expects($this->once())->method('getOptions')->will($this->returnValue($optionsSet));
        $configOption->expects($this->once())->method('validate')->will($this->returnValue([]));

        $this->collector
            ->expects($this->exactly(2))
            ->method('collectOptionsLists')
            ->will($this->returnValue([$configOption]));

        $this->configModel->validate(['Fake' => null]);
    }

    public function testProcess()
    {
        $testSet1 = [
            ConfigFilePool::APP_CONFIG => [
                'segment' => [
                    'someKey' => 'value',
                    'test' => 'value1'
                ]
            ]
        ];

        $testSet2 = [
            ConfigFilePool::APP_CONFIG => [
                'segment' => [
                    'test' => 'value2'
                ]
            ]
        ];

        $testSetExpected1 = [
            ConfigFilePool::APP_CONFIG => [
                'segment' => [
                    'someKey' => 'value',
                    'test' => 'value1'
                ]
            ]
        ];

        $testSetExpected2 = [
            ConfigFilePool::APP_CONFIG => [
                'segment' => [
                    'test' => 'value2'
                ]
            ]
        ];

        $configData1 = clone $this->configData;
        $configData2 = clone $this->configData;

        $configData1->expects($this->any())
            ->method('getData')
            ->will($this->returnValue($testSet1[ConfigFilePool::APP_CONFIG]));
        $configData1->expects($this->any())->method('getFileKey')->will($this->returnValue(ConfigFilePool::APP_CONFIG));
        $configData1->expects($this->once())->method('isOverrideWhenSave')->willReturn(false);

        $configData2->expects($this->any())
            ->method('getData')
            ->will($this->returnValue($testSet2[ConfigFilePool::APP_CONFIG]));
        $configData2->expects($this->any())->method('getFileKey')->will($this->returnValue(ConfigFilePool::APP_CONFIG));
        $configData2->expects($this->once())->method('isOverrideWhenSave')->willReturn(false);

        $configOption = $this->configOptionsList;
        $configOption->expects($this->once())
            ->method('createConfig')
            ->will($this->returnValue([$configData1, $configData2]));

        $configOptionsList = [
            'Fake_Module' => $configOption
        ];
        $this->collector->expects($this->once())
            ->method('collectOptionsLists')
            ->will($this->returnValue($configOptionsList));

        $this->writer->expects($this->at(0))->method('saveConfig')->with($testSetExpected1);
        $this->writer->expects($this->at(1))->method('saveConfig')->with($testSetExpected2);

        $this->configModel->process([]);
    }

    public function testProcessException()
    {
        $this->expectException('Exception');
        $this->expectExceptionMessage('In module : Fake_ModuleConfigOption::createConfig');
        $configOption = $this->configOptionsList;
        $configOption->expects($this->once())
            ->method('createConfig')
            ->will($this->returnValue([null]));

        $wrongData = [
            'Fake_Module' => $configOption
        ];

        $this->collector->expects($this->once())->method('collectOptionsLists')->will($this->returnValue($wrongData));

        $this->configModel->process([]);
    }

    public function testWritePermissionErrors()
    {
        $this->expectException('Exception');
        $this->expectExceptionMessage('Missing write permissions to the following paths:');
        $this->filePermissions->expects($this->once())->method('getMissingWritablePathsForInstallation')
            ->willReturn(['/a/ro/dir', '/media']);
        $this->configModel->process([]);
    }
}
