<?php
namespace Whsuite\Composer;

use Composer\Package\PackageInterface;

/**
 * Enter descriptions here
 *
 * @author Thomas Maroschik <tmaroschik@dfau.de>
 */
class AddonInstaller implements \Composer\Installer\InstallerInterface {

    /**
     * @var string
     */
    protected $addonDir;

    /**
     * @var \Composer\Composer
     */
    protected $composer;

    /**
     * @var \Composer\Downloader\DownloadManager
     */
    protected $downloadManager;

    /**
     * @var \Composer\Util\Filesystem
     */
    protected $filesystem;

    /**
     * @param \Composer\Composer $composer
     * @param \Composer\Util\Filesystem $filesystem
     */
    public function __construct(\Composer\Composer $composer, \Composer\Util\Filesystem $filesystem = NULL) {
        $this->composer = $composer;
        $this->downloadManager = $composer->getDownloadManager();

        $this->filesystem = $filesystem ? : new \Composer\Util\Filesystem();
        $this->addonDir = 'app' . DIRECTORY_SEPARATOR . 'addons';
    }

    /**
     * Decides if the installer supports the given type
     *
     * @param  string $packageType
     * @return bool
     */
    public function supports($packageType) {
        return $packageType === 'whsuite-addon';
    }

    /**
     * Checks that provided package is installed.
     *
     * @param \Composer\Repository\InstalledRepositoryInterface $repo repository in which to check
     * @param PackageInterface $package package instance
     *
     * @return bool
     */
    public function isInstalled(\Composer\Repository\InstalledRepositoryInterface $repo, PackageInterface $package) {
        return $repo->hasPackage($package) && is_readable($this->getInstallPath($package));
    }

    /**
     * Installs specific package.
     *
     * @param \Composer\Repository\InstalledRepositoryInterface $repo repository in which to check
     * @param PackageInterface $package package instance
     */
    public function install(\Composer\Repository\InstalledRepositoryInterface $repo, PackageInterface $package) {
        $this->initializeAddonDir();

        $this->installCode($package);
        if (!$repo->hasPackage($package)) {
            $repo->addPackage(clone $package);
        }
    }

    /**
     * Updates specific package.
     *
     * @param \Composer\Repository\InstalledRepositoryInterface $repo repository in which to check
     * @param PackageInterface $initial already installed package version
     * @param PackageInterface $target updated version
     *
     * @throws \InvalidArgumentException if $initial package is not installed
     */
    public function update(\Composer\Repository\InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target) {
        if (!$repo->hasPackage($initial)) {
            throw new \InvalidArgumentException('Package is not installed: ' . $initial);
        }

        $this->initializeAddonDir();

        $this->updateCode($initial, $target);
        $repo->removePackage($initial);
        if (!$repo->hasPackage($target)) {
            $repo->addPackage(clone $target);
        }
    }

    /**
     * Uninstalls specific package.
     *
     * @param \Composer\Repository\InstalledRepositoryInterface $repo repository in which to check
     * @param PackageInterface $package package instance
     *
     * @throws \InvalidArgumentException if $initial package is not installed
     */
    public function uninstall(\Composer\Repository\InstalledRepositoryInterface $repo, PackageInterface $package) {
        if (!$repo->hasPackage($package)) {
            throw new \InvalidArgumentException('Package is not installed: ' . $package);
        }

        $this->removeCode($package);
        $repo->removePackage($package);
    }

    /**
     * Returns the installation path of a package
     *
     * @param  PackageInterface $package
     * @return string           path
     */
    public function getInstallPath(PackageInterface $package) {

        list(, $name) = explode('/', $package->getName(), 2);
        $name = str_replace('addon-', '', strtolower($name));
        $name = str_replace('-', '_', $name);

        return $this->addonDir . DIRECTORY_SEPARATOR . $name;
    }

    /**
     * @param PackageInterface $package
     */
    protected function installCode(PackageInterface $package) {
        $this->downloadManager->download($package, $this->getInstallPath($package));
    }

    /**
     * @param PackageInterface $initial
     * @param PackageInterface $target
     */
    protected function updateCode(PackageInterface $initial, PackageInterface $target) {
        $initialDownloadPath = $this->getInstallPath($initial);
        $targetDownloadPath = $this->getInstallPath($target);
        if ($targetDownloadPath !== $initialDownloadPath) {
            // if the target and initial dirs intersect, we force a remove + install
            // to avoid the rename wiping the target dir as part of the initial dir cleanup
            if (substr($initialDownloadPath, 0, strlen($targetDownloadPath)) === $targetDownloadPath
                || substr($targetDownloadPath, 0, strlen($initialDownloadPath)) === $initialDownloadPath
            ) {
                $this->removeCode($initial);
                $this->installCode($target);

                return;
            }

            $this->filesystem->rename($initialDownloadPath, $targetDownloadPath);
        }
        $this->downloadManager->update($initial, $target, $targetDownloadPath);
    }

    /**
     * @param PackageInterface $package
     */
    protected function removeCode(PackageInterface $package) {
        $this->downloadManager->remove($package, $this->getInstallPath($package));
    }

    /**
     *
     */
    protected function initializeAddonDir() {
        $this->filesystem->ensureDirectoryExists($this->addonDir);
        $this->addonDir = realpath($this->addonDir);
    }
}