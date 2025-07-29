<?php

declare(strict_types=1);

namespace Sonrac\ComposerAuthenticatedRepositoryPlugin;

use Composer\Composer;
use Composer\Downloader\DownloadManager;
use Composer\Downloader\ZipDownloader;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\Installer\LibraryInstaller;
use Composer\Installer\PluginInstaller;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Repository\RepositoryManager;
use Composer\Util\Loop;
use ReflectionClass;
use Sonrac\ComposerAuthenticatedRepositoryPlugin\Repository\AuthenticatedComposerRepository;
use Sonrac\ComposerAuthenticatedRepositoryPlugin\Repository\AuthenticatedHttpDownloader;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    public const NAME = 'composer-authenticated-plugin';

    private Composer $composer;
    private IOInterface $io;
    private AuthenticatedHttpDownloader $httpDownloader;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
        $extra = $composer->getPackage()->getExtra();
        if (!array_key_exists(self::NAME, $extra)) {
            $extra[self::NAME] = ['repositories' => []];
        }

        $pluginConfig = $extra[self::NAME] ?? ['repositories' => []];

        foreach ($pluginConfig['repositories'] as $repository) {
            if (!array_key_exists('owner', $repository) || !array_key_exists('name', $repository)) {
                $io->warning(
                    sprintf(
                        'You must set `owner` & `name` params for repository config. Fix config for plugin %s',
                        self::NAME,
                    ),
                );
            }
        }

        // Get authentication credentials
        $githubToken = $this->getGitHubToken();
        $httpBasicAuth = $this->getHttpBasicAuth();

        // Create authenticated HTTP downloader
        $this->httpDownloader = new AuthenticatedHttpDownloader(
            Factory::createHttpDownloader($this->io, $this->composer->getConfig()),
            $githubToken,
            $httpBasicAuth,
            $pluginConfig['repositories'],
            $this->io,
        );

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

    public function onDownload(PreFileDownloadEvent $preFileDownloadEvent): void
    {
        $processedUrl = $preFileDownloadEvent->getProcessedUrl();
        $context = $preFileDownloadEvent->getContext();

        // Debug: Log the URL being processed
        $this->io->write(sprintf('<info>Processing URL: %s</info>', $processedUrl));
        
        // Debug: Check if we need auth headers
        $needsAuth = $this->httpDownloader->isNeedAuthHeaders($processedUrl);
        $this->io->write(sprintf('<info>Needs auth headers: %s</info>', $needsAuth ? 'YES' : 'NO'));

        // Debug: Check if link is supported for get release link
        $isNeedGetReleaseUrl = $this->httpDownloader->isNeedGetReleaseUrl($processedUrl);
        $this->io->write(
            sprintf('<info>Link supported for get asset download url: %s</info>', $isNeedGetReleaseUrl ? 'YES' : 'NO'),
        );

        // Debug: Show configured repositories
        $extra = $this->composer->getPackage()->getExtra();
        $pluginConfig = $extra[self::NAME] ?? ['repositories' => []];
        $this->io->write(sprintf('<info>Configured repositories: %s</info>', json_encode($pluginConfig['repositories'])));

        if ($needsAuth) {
            if ($isNeedGetReleaseUrl) {
                $assetUrl = $this->httpDownloader->getGitHubAssetApiUrl($processedUrl);

                if ($assetUrl !== null) {
                    $preFileDownloadEvent->setProcessedUrl($assetUrl);
                    $this->io->write(
                        sprintf('<info>Converted to asset URL: from %s to %s</info>', $processedUrl, $assetUrl)
                    );
                }
            }

            $transportOptions = [
                'http' => [
                    'header' => [
                        'Accept: application/octet-stream',
                    ],
                ],
            ];

            if ($context instanceof PackageInterface) {
                $context->setTransportOptions(
                    array_merge(
                        $context->getTransportOptions(),
                        $transportOptions,
                    ),
                );
            }

            $preFileDownloadEvent->setTransportOptions(
                array_merge(
                    $preFileDownloadEvent->getTransportOptions(),
                    $transportOptions,
                ),
            );

            $this->io->debug('Context class' . get_class($context));
            $this->io->debug('Processed url' . $processedUrl);
            $this->io->debug('Is package context: ' .  $context instanceof PackageInterface ? 'YES' : 'NO');
        } else {
            $this->io->info('URL does not need auth headers - this might be the issue!');
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            PluginEvents::PRE_FILE_DOWNLOAD => ['onDownload'],
        ];
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
                $this->httpDownloader,
                $repository['owner'],
                $repository['name'],
            );

            $repositoryManager->prependRepository($repository);
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
