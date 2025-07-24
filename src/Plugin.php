<?php

declare(strict_types=1);

namespace Sonrac\ComposerAuthenticatedRepositoryPlugin;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Repository\RepositoryManager;
use Sonrac\ComposerAuthenticatedRepositoryPlugin\Repository\AuthenticatedComposerRepository;
use RuntimeException;

class Plugin implements PluginInterface
{
    public const NAME = 'composer-authenticated-plugin';

    private Composer $composer;
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
        $extra = $composer->getPackage()->getExtra();
        if (!array_key_exists(self::NAME, $extra)) {
            throw new RuntimeException(
                sprintf('You must set extra params for plugin %s', self::NAME)
            );
        }

        $pluginConfig = $extra[self::NAME];

        if (!array_key_exists('repositories', $pluginConfig)) {
            throw new RuntimeException(
                sprintf('You must set repositories for plugin %s', self::NAME)
            );
        }

        $repositoryManager = $composer->getRepositoryManager();
        $this->registerRepositoryType($repositoryManager, $pluginConfig);
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // Cleanup if needed
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // Cleanup if needed
    }

    /**
     * @param array{repositories: array<int, array{url: string}>} $pluginConfig
     */
    private function registerRepositoryType(RepositoryManager $repositoryManager, array $pluginConfig): void
    {
        foreach ($pluginConfig['repositories'] as $repository) {
            $repository = new AuthenticatedComposerRepository(
                $repository,
                $this->io,
                $this->composer->getConfig(),
                Factory::createHttpDownloader(
                    $this->io,
                    $this->composer->getConfig(),
                ),
            );

            $repositoryManager->prependRepository($repository);
        }
    }
} 
