<?php

declare(strict_types=1);

namespace Sonrac\ComposerAuthenticatedRepositoryPlugin\Tests;

use PHPUnit\Framework\TestCase;
use Sonrac\ComposerAuthenticatedRepositoryPlugin\Repository\AuthenticatedComposerRepository;
use Sonrac\ComposerAuthenticatedRepositoryPlugin\Repository\AuthenticatedHttpDownloader;

class AuthenticatedRepositoryTest extends TestCase
{
    public function testAuthenticatedHttpDownloaderCreation(): void
    {
        $mockDownloader = $this->createMock(\Composer\Util\HttpDownloader::class);
        
        $downloader = new AuthenticatedHttpDownloader(
            $mockDownloader,
            'test-token',
            ['username', 'password'],
            ['url' => 'https://api.github.com']
        );
        
        $this->assertInstanceOf(AuthenticatedHttpDownloader::class, $downloader);
    }

    public function testGitHubUrlDetection(): void
    {
        $mockDownloader = $this->createMock(\Composer\Util\HttpDownloader::class);
        
        $downloader = new AuthenticatedHttpDownloader(
            $mockDownloader,
            'test-token',
            null,
            []
        );
        
        // Test GitHub URL detection using reflection
        $reflection = new \ReflectionClass($downloader);
        $method = $reflection->getMethod('isGitHubUrl');
        $method->setAccessible(true);
        
        $this->assertTrue($method->invoke($downloader, 'https://api.github.com/repos/test'));
        $this->assertTrue($method->invoke($downloader, 'https://github.com/test/repo'));
        $this->assertFalse($method->invoke($downloader, 'https://example.com/test'));
    }

    public function testAuthenticationHeaders(): void
    {
        $mockDownloader = $this->createMock(\Composer\Util\HttpDownloader::class);
        
        $downloader = new AuthenticatedHttpDownloader(
            $mockDownloader,
            'test-token',
            ['user', 'pass'],
            []
        );
        
        // Test authentication headers using reflection
        $reflection = new \ReflectionClass($downloader);
        $method = $reflection->getMethod('addAuthenticationHeaders');
        $method->setAccessible(true);
        
        $options = [];
        $result = $method->invoke($downloader, 'https://api.github.com/test', $options);
        
        $this->assertArrayHasKey('http', $result);
        $this->assertArrayHasKey('header', $result['http']);
        $this->assertContains('Authorization: token test-token', $result['http']['header']);
        $this->assertContains('Authorization: Basic ' . base64_encode('user:pass'), $result['http']['header']);
    }
} 
