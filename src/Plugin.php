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

    public function onDownload(PreFileDownloadEvent $preFileDownloadEvent): void
    {
        $processedUrl = $preFileDownloadEvent->getProcessedUrl();
        $context = $preFileDownloadEvent->getContext();
        $transportOptions = [];

        if (
            $this->httpDownloader->isNeedAuthHeaders($processedUrl)
        ) {
            if ($this->httpDownloader->isLinkSupported($processedUrl)) {
                $assetUrl = $this->httpDownloader->getGitHubAssetApiUrl($processedUrl);

                if ($assetUrl !== null) {
                    $preFileDownloadEvent->setProcessedUrl($assetUrl);
                }
            }

            if ($context instanceof PackageInterface) {
                $transportOptions = $this->httpDownloader->addAuthenticationHeaders(
                    $processedUrl,
                    $context->getTransportOptions(),
                    'application/zip'
                );
            }

            var_dump(get_class($context), $processedUrl, $context instanceof PackageInterface);
            var_dump($this->httpDownloader->addAuthenticationHeaders(
                $processedUrl,
                $context->getTransportOptions()
            ));

            var_dump('Transport options', $transportOptions);
        }

        if (count($transportOptions) > 0) {
            $metadataOptions = $preFileDownloadEvent->getTransportOptions();
            $preFileDownloadEvent->setTransportOptions(
                array_merge($metadataOptions, $transportOptions)
            );

            if ($context instanceof PackageInterface) {
                $context->setTransportOptions($transportOptions);
            }
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

    /**
     * @param array{repositories: array<int, array{url: string, owner: string, name: string, force_download_via_plugin: bool}>} $pluginConfig
     */
    private function interceptDownloadManager(array $pluginConfig): void
    {
        /** @var DownloadManager $downloadManager */
        $downloadManager = $this->composer->getDownloadManager();

//        /** @var ZipDownloader $zipDownloader */
//        $zipDownloader = $downloadManager->getDownloader('zip');
//        $authenticatedDownloader->enableAsync();
//
//        // Replace the download manager's HTTP downloader using reflection
//        try {
//            $reflection = new \ReflectionClass($zipDownloader);
//            $httpDownloaderProperty = $reflection->getProperty('httpDownloader');
//            $httpDownloaderProperty->setAccessible(true);
//            $httpDownloaderProperty->setValue($zipDownloader, $authenticatedDownloader);
//            $downloadManager->setDownloader('zip', $zipDownloader);
//        } catch (\ReflectionException $e) {
//            // If reflection fails, log the error but don't break the plugin
//            error_log('Failed to intercept download manager: ' . $e->getMessage());
//        }

//        $installationManager = $this->composer->getInstallationManager();
//        $reflection = new ReflectionClass($installationManager);
//        $prop = $reflection->getProperty('installers');
//        $prop->setAccessible(true);
//        $installers = $prop->getValue($installationManager);
//        foreach ($installers as $installer) {
//            if ($installer instanceof LibraryInstaller && !$installer instanceof PluginInstaller) {
//                $reflection = new ReflectionClass($installer);
//                $downloaderProp = $reflection->getProperty('downloadManager');
//                $downloaderProp->setAccessible(true);
//                /** @var DownloadManager $installerHttpDownloader */
//                $installerHttpDownloader = $downloaderProp->getValue($installer);
//
//                $installerHttpDownloader->setDownloader('zip', $zipDownloader);
//            }
//        }
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
