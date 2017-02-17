<?php

/**
 * @file
 * Contains DruStack\Composer\GenerateMetadata\Plugin.
 */

namespace DruStack\Composer\GenerateMetadata;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Installer\PackageEvent;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use Symfony\Component\Process\Process;

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
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_PACKAGE_INSTALL => 'generateInfoMetadata',
            ScriptEvents::POST_PACKAGE_UPDATE => 'generateInfoMetadata',
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
        $installation_manager = $this->composer->getInstallationManager();

        $package = $op->getJobType() == 'update'
            ? $op->getTargetPackage()
            : $op->getPackage();
        $install_path = $installation_manager->getInstallPath($package);

        if (preg_match('/^drupal-/', $package->getType())) {
            if (preg_match('/^dev-/', $package->getPrettyVersion())) {
                $project = preg_replace('/^.*\//', '', $package->getName());
                $version = preg_replace('/^dev-(.*)/', '8.x-$1-dev', $package->getPrettyVersion());
                $branch = preg_replace('/^([0-9]*\.x-[0-9]*).*$/', '$1', $version);
                $datestamp = time();

                // Compute the rebuild version string for a project.
                $version = $this->computeRebuildVersion($install_path, $branch) ?: $version;

                // Generate version information for `.info.yml` files in YAML format.
                $finder = new Finder();
                $finder
                    ->files()
                    ->in($install_path)
                    ->name('*.info.yml')
                    ->notContains('datestamp:');
                foreach ($finder as $file) {
                    file_put_contents(
                        $file->getRealpath(),
                        $this->generateInfoYamlMetadata($version, $project, $datestamp),
                        FILE_APPEND
                    );
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
    protected function computeRebuildVersion($install_path, $branch)
    {
        $version = '';
        $branch_preg = preg_quote($branch);

        $process = new Process("cd $install_path; git describe --tags");
        $process->run();
        if ($process->isSuccessful()) {
            $last_tag = strtok($process->getOutput(), "\n");
            // Make sure the tag starts as Drupal formatted (for eg.
            // 7.x-1.0-alpha1) and if we are on a proper branch (ie. not master)
            // then it's on that branch.
            if (preg_match('/^(?<drupalversion>'.$branch_preg.'\.\d+(?:-[^-]+)?)(?<gitextra>-(?<numberofcommits>\d+-)g[0-9a-f]{7})?$/', $last_tag, $matches)) {
                if (isset($matches['gitextra'])) {
                    // If we found additional git metadata (in particular, number of commits)
                    // then use that info to build the version string.
                    $version = $matches['drupalversion'].'+'.$matches['numberofcommits'].'dev';
                } else {
                    // Otherwise, the branch tip is pointing to the same commit as the
                    // last tag on the branch, in which case we use the prior tag and
                    // add '+0-dev' to indicate we're still on a -dev branch.
                    $version = $last_tag.'+0-dev';
                }
            }
        }

        return $version;
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

# Information add by composer on {$date}
core: "{$core}"
project: "{$project}"
version: "{$version}"
datestamp: "{$datestamp}"
METADATA;

        return $info;
    }
}
