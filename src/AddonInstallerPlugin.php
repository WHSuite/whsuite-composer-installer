<?php

namespace Whsuite\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class AddonInstallerPlugin implements PluginInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
        $composer->getInstallationManager()->addInstaller(
            new AddonInstaller(
                $composer,
                new \Composer\Util\Filesystem
            )
        );
    }
}
