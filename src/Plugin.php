<?php

declare(strict_types=1);

namespace Sonrac\ComposerAuthenticatedRepositoryPlugin;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Repository\RepositoryManager;
use Sonrac\ComposerAuthenticatedRepositoryPlugin\Repository\AuthenticatedComposerRepository;
use Sonrac\ComposerAuthenticatedRepositoryPlugin\Repository\AuthenticatedHttpDownloader;

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
            error_log(
                sprintf('You must set extra params for plugin %s', self::NAME)
            );

            return;
        }

        $pluginConfig = $extra[self::NAME];

        if (!array_key_exists('repositories', $pluginConfig)) {
            error_log(
                sprintf('You must set repositories for plugin %s', self::NAME)
            );

            return;
        }

        foreach ($pluginConfig['repositories'] as $repository) {
            if (!array_key_exists('owner', $repository) || !array_key_exists('name', $repository)) {
                error_log(
                    sprintf(
                        'You must set `owner` & `name` params for repository config. Fix config for plugin %s',
                        self::NAME,
                    ),
                );

                return;
            }
        }

        $repositoryManager = $composer->getRepositoryManager();
        $this->registerRepositoryType($repositoryManager, $pluginConfig);

        // Also intercept the download manager to handle ZIP file downloads
        $this->interceptDownloadManager($pluginConfig);
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
     * @param array{repositories: array<int, array{url: string, owner: string, name: string}>} $pluginConfig
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
                $repository['owner'],
                $repository['name'],
            );

            $repositoryManager->prependRepository($repository);
        }
    }

    /**
     * Intercept the download manager to handle ZIP file downloads with authentication
     *
     * @param array{repositories: array<int, array{url: string}>} $pluginConfig
     */
    private function interceptDownloadManager(array $pluginConfig): void
    {
        $downloadManager = $this->composer->getDownloadManager();

        // Get authentication credentials
        $githubToken = $this->getGitHubToken();
        $httpBasicAuth = $this->getHttpBasicAuth();

        // Create authenticated HTTP downloader
        $authenticatedDownloader = new AuthenticatedHttpDownloader(
            Factory::createHttpDownloader($this->io, $this->composer->getConfig()),
            $githubToken,
            $httpBasicAuth,
            $pluginConfig,
        );

        // Replace the download manager's HTTP downloader using reflection
        try {
            $reflection = new \ReflectionClass($downloadManager);
            $httpDownloaderProperty = $reflection->getProperty('httpDownloader');
            $httpDownloaderProperty->setAccessible(true);
            $httpDownloaderProperty->setValue($downloadManager, $authenticatedDownloader);
        } catch (\ReflectionException $e) {
            // If reflection fails, log the error but don't break the plugin
            error_log('Failed to intercept download manager: ' . $e->getMessage());
        }
    }

    private function getGitHubToken(): ?string
    {
        $config = $this->composer->getConfig();
        $githubTokens = $config->get('github-oauth') ?? [];

        // Return token for api.github.com if available
        return $githubTokens['api.github.com'] ?? $githubTokens['github.com'] ?? null;
    }

    private function getHttpBasicAuth(): ?array
    {
        $config = $this->composer->getConfig();
        $httpBasicAuth = $config->get('http-basic') ?? [];

        // Return credentials for any configured host
        foreach ($httpBasicAuth as $host => $credentials) {
            if (is_array($credentials) && isset($credentials['username']) && isset($credentials['password'])) {
                return [$credentials['username'], $credentials['password']];
            }
        }

        return null;
    }
} 
