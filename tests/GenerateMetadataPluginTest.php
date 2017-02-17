<?php

namespace DruStack\Composer\GenerateMetadata\Tests;

use Composer\Composer;
use Composer\Config;
use Composer\Factory;
use Composer\Util\Filesystem;
use DruStack\Composer\GenerateMetadata\Plugin;
use PHPUnit\Framework\TestCase;

class GenerateMetadataPluginTest extends TestCase
{
    private $composer;
    private $config;
    private $io;
    private $rm;
    private $archiveDir;
    private $vendorDir;
    private $binDir;

    public function setUp()
    {
        parent::setUp();

        $this->fs = new Filesystem();

        $this->io = $this->createMock('Composer\IO\IOInterface');

        $this->archiveDir = mkdir(uniqid(sys_get_temp_dir().DIRECTORY_SEPARATOR), 0777, true);
        $this->vendorDir = mkdir(uniqid(sys_get_temp_dir().DIRECTORY_SEPARATOR), 0777, true);
        $this->binDir = mkdir(uniqid(sys_get_temp_dir().DIRECTORY_SEPARATOR), 0777, true);

        $config = [
            'config' => [
                'archive-dir' => $this->archiveDir,
                'vendor-dir' => $this->vendorDir,
                'bin-dir' => $this->binDir,
            ],
            'repositories' => [
                [
                    'type' => 'composer',
                    'url' => 'https://packages.drupal.org/8',
                ],
            ],
        ];
        $factory = new Factory();
        $this->composer = $factory->createComposer($this->io, $config);
    }

    public function tearDown()
    {
        $this->fs->removeDirectory($this->archiveDir);
        $this->fs->removeDirectory($this->vendorDir);
        $this->fs->removeDirectory($this->binDir);
    }

    public function testActivate()
    {
        $plugin = new Plugin();
        $plugin->activate($this->composer, $this->io);
    }
}
