<?php

/**
 * @file
 * Contains DruStack\Composer\GenerateMetadata\Plugin.
 */

namespace DruStack\Composer\GenerateMetadata;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Symfony\Component\Finder\Finder;

/**
 * Class Plugin.
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var \Composer\Composer
     */
    protected $composer;

    /**
     * @var \Composer\IO\IOInterfac
     */
    protected $io;

    /**
     * @var \Composer\Installer\InstallationManager
     */
    protected $installationManager;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var string
     */
    protected $project;

    /**
     * @var string
     */
    protected $datastamp;

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->installationManager = $composer->getInstallationManager();
    }

    /**
     * {@inheritDoc}
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => ['generateInfoMetadata', -99],
            PackageEvents::POST_PACKAGE_UPDATE => ['generateInfoMetadata', -99],
        ];
    }

    /**
     * Inject metadata into all .info files for a given project.
     *
     * @see drush_pm_inject_info_file_metadata()
     */
    public function generateInfoMetadata(PackageEvent $event)
    {
        $op = $event->getOperation();
        $package = ($op instanceof InstallOperation)
            ? $op->getPackage()
            : $op->getTargetPackage();
        $extra = $package->getExtra();

        if (preg_match('/^drupal-/', $package->getType())) {
            if (preg_match('/^dev-/', $package->getPrettyVersion())) {
                // Compute the rebuild version string for a project.
                $this->project = preg_replace('/^drupal\//', '', $package->getName());
                $this->version = $extra['drupal']['version']
                    ?: preg_replace('/^dev-(.*)/', '$1-dev', $package->getPrettyVersion());
                $this->datestamp = $extra['drupal']['datestamp']
                    ?: time();

                $this->io->write('  - Generating metadata for <info>'.$this->project.'</info>');

                // Generate version information for `.info.yml` files in YAML format.
                $finder = new Finder();
                $finder
                    ->files()
                    ->in($this->installationManager->getInstallPath($package))
                    ->name('*.info.yml')
                    ->notContains('datestamp:');
                foreach ($finder as $file) {
                    // Remove `version` and `project` lines.
                    file_put_contents(
                        $file->getRealpath(),
                        preg_replace(
                            '/^\s*(version|project)\s*:.*\n/m',
                            '',
                            file_get_contents($file->getRealpath())
                        )
                    );
                    // Append generated version information.
                    file_put_contents(
                        $file->getRealpath(),
                        $this->generateInfoYamlMetadata(),
                        FILE_APPEND
                    );
                }
            }
        }
    }

    /**
     * Generate version information for `.info.yml` files in YAML format.
     *
     * @see _drush_pm_generate_info_yaml_metadata()
     */
    protected function generateInfoYamlMetadata()
    {
        $date = date('Y-m-d', $this->datestamp);
        $info = <<<METADATA

# Information add by drustack/composer-generate-metadata on {$date}
version: "{$this->version}"
project: "{$this->project}"
datestamp: "{$this->datestamp}"

METADATA;

        return $info;
    }
}
