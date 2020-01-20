<?php

/**
 * @file
 * Contains DruStack\Composer\GenerateMetadata\Plugin.
 */

namespace DruStack\Composer\GenerateMetadata;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Installer\InstallationManager;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\Plugin\PluginInterface;
use Composer\Repository\ComposerRepository;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * Class Plugin.
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * The expected Drupal core version.
     */
    protected $core;

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
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->io = $io;
        $this->composer = $composer;
        $this->installationManager = $composer->getInstallationManager();

        // Check Drupal core version.
        $repositories = $composer->getRepositoryManager()->getRepositories();
        foreach ($repositories as $repository) {
            if ($repository instanceof ComposerRepository) {
                $repoConfig = $repository->getRepoConfig();
                if (preg_match('/packages\.drupal\.org\/([0-9]*)$/', $repoConfig['url'], $matches)) {
                    $this->core = $matches[1];
                    break;
                }
            }
        }
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
        $package = $op->getJobType() == 'update'
            ? $op->getTargetPackage()
            : $op->getPackage();
        $installPath = $this->installationManager->getInstallPath($package);

        if (preg_match('/^drupal-/', $package->getType())) {
            if (preg_match('/^dev-/', $package->getPrettyVersion())) {
                $name = $package->getName();
                $project = preg_replace('/^.*\//', '', $name);
                $version = preg_replace('/^dev-(.*)/', $this->core.'.x-$1-dev', $package->getPrettyVersion());
                $branch = preg_replace('/^([0-9]*\.x-[0-9]*).*$/', '$1', $version);
                $datestamp = time();

                $this->io->write('  - Generating metadata for <info>'.$name.'</info>');

                // Compute the rebuild version string for a project.
                $version = $this->computeRebuildVersion($installPath, $branch) ?: $version;

                if ($this->core == '7') {
                    // Generate version information for `.info` files in ini format.
                    $finder = new Finder();
                    $finder
                        ->files()
                        ->in($installPath)
                        ->name('*.info')
                        ->notContains('datestamp =');
                    foreach ($finder as $file) {
                        // Remove `version` and `project` lines.
                        file_put_contents(
                            $file->getRealpath(),
                            preg_replace(
                                '/^\s*(version|project)\s*=.*\n/m',
                                '',
                                file_get_contents($file->getRealpath())
                            )
                        );
                        // Append generated version information.
                        file_put_contents(
                            $file->getRealpath(),
                            $this->generateInfoIniMetadata($version, $project, $datestamp),
                            FILE_APPEND
                        );
                    }
                } else {
                    // Generate version information for `.info.yml` files in YAML format.
                    $finder = new Finder();
                    $finder
                        ->files()
                        ->in($installPath)
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
                            $this->generateInfoYamlMetadata($version, $project, $datestamp),
                            FILE_APPEND
                        );
                    }
                }
            }
        }
    }

    /**
     * Helper function to compute the rebulid version string for a project.
     *
     * This does some magic in Git to find the latest release tag along
     * the branch we're packaging from, count the number of commits since
     * then, and use that to construct this fancy alternate version string
     * which is useful for the version-specific dependency support in Drupal
     * 7 and higher.
     *
     * NOTE: A similar function lives in git_deploy and in the drupal.org
     * packaging script (see DrupalorgProjectPackageRelease.class.php inside
     * drupalorg/drupalorg_project/plugins/release_packager). Any changes to the
     * actual logic in here should probably be reflected in the other places.
     *
     * @see drush_pm_git_drupalorg_compute_rebuild_version()
     */
    protected function computeRebuildVersion($installPath, $branch)
    {
        $version = '';
        $branchPreg = preg_quote($branch);

        $process = new Process("cd $installPath; git describe --tags");
        $process->run();
        if ($process->isSuccessful()) {
            $lastTag = strtok($process->getOutput(), "\n");
            // Make sure the tag starts as Drupal formatted (for eg.
            // 7.x-1.0-alpha1) and if we are on a proper branch (ie. not master)
            // then it's on that branch.
            if (preg_match('/^(?<drupalversion>'.$branchPreg.'\.\d+(?:-[^-]+)?)(?<gitextra>-(?<numberofcommits>\d+-)g[0-9a-f]{7})?$/', $lastTag, $matches)) {
                if (isset($matches['gitextra'])) {
                    // If we found additional git metadata (in particular, number of commits)
                    // then use that info to build the version string.
                    $version = $matches['drupalversion'].'+'.$matches['numberofcommits'].'dev';
                } else {
                    // Otherwise, the branch tip is pointing to the same commit as the
                    // last tag on the branch, in which case we use the prior tag and
                    // add '+0-dev' to indicate we're still on a -dev branch.
                    $version = $lastTag.'+0-dev';
                }
            }
        }

        return $version;
    }

    /**
     * Generate version information for `.info` files in ini format.
     *
     * @see _drush_pm_generate_info_ini_metadata()
     */
    protected function generateInfoIniMetadata($version, $project, $datestamp)
    {
        $core = preg_replace('/^([0-9]).*$/', '$1.x', $version);
        $date = date('Y-m-d', $datestamp);
        $info = <<<METADATA

; Information add by drustack/composer-generate-metadata on {$date}
version = "{$version}"
core = "{$core}"
project = "{$project}"
datestamp = "{$datestamp}"
METADATA;

        return $info;
    }

    /**
     * Generate version information for `.info.yml` files in YAML format.
     *
     * @see _drush_pm_generate_info_yaml_metadata()
     */
    protected function generateInfoYamlMetadata($version, $project, $datestamp)
    {
        $core = preg_replace('/^([0-9]).*$/', '$1.x', $version);
        $date = date('Y-m-d', $datestamp);
        $info = <<<METADATA

# Information add by drustack/composer-generate-metadata on {$date}
project: "{$project}"
version: "{$version}"
datestamp: "{$datestamp}"
METADATA;

        return $info;
    }
}
