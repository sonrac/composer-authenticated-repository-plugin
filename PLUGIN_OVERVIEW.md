# Composer Authenticated Repository Plugin - Implementation Overview

## Plugin Architecture

The plugin implements authentication support by intercepting file downloads using Composer's `PreFileDownloadEvent`. It automatically adds authentication headers to requests for configured repositories without requiring changes to existing Composer workflows.

## Core Components

### 1. Main Plugin Class (`Plugin.php`)

**Purpose**: Entry point that implements `PluginInterface` and `EventSubscriberInterface`

**Key Features**:
- Implements `PluginInterface` for basic plugin lifecycle
- Implements `EventSubscriberInterface` to subscribe to `PreFileDownloadEvent`
- Creates authenticated HTTP downloader with credentials
- Registers custom repository types
- Provides comprehensive debug logging

**Methods**:
- `activate()`: Called when plugin is activated, sets up authentication and repositories
- `onDownload()`: Handles `PreFileDownloadEvent`, adds authentication headers
- `getSubscribedEvents()`: Returns event subscriptions

### 2. Authenticated Repository (`AuthenticatedComposerRepository.php`)

**Purpose**: Wraps standard Composer repository with authentication support

**Key Features**:
- Extends `ComposerRepository`
- Stores repository configuration (owner, name, URL)
- Uses authenticated HTTP downloader for requests

**Methods**:
- `__construct()`: Creates repository with authentication configuration

### 3. Authenticated HTTP Downloader (`AuthenticatedHttpDownloader.php`)

**Purpose**: Provides authentication header injection and URL processing

**Key Features**:
- Extends `HttpDownloader`
- Adds authentication headers to requests
- Supports GitHub tokens and HTTP basic auth
- Detects GitHub URLs automatically
- Handles repository matching logic
- Provides debug logging for troubleshooting

**Methods**:
- `get()`: Adds auth headers to GET requests
- `add()`: Adds auth headers to queued requests
- `copy()`: Adds auth headers to copy requests
- `addCopy()`: Handles file downloads with authentication
- `addAuthenticationHeaders()`: Injects auth headers
- `isGitHubUrl()`: Detects GitHub URLs
- `isNeedAuthHeaders()`: Determines if URL needs authentication
- `isNeedGetReleaseUrl()`: Checks if URL is a GitHub release download
- `getGitHubAssetApiUrl()`: Converts browser URLs to API URLs

## Authentication Flow

### 1. Plugin Activation
```php
// Plugin reads configuration from composer.json
$pluginConfig = $extra[self::NAME] ?? ['repositories' => []];

// Creates authenticated HTTP downloader
$this->httpDownloader = new AuthenticatedHttpDownloader(
    Factory::createHttpDownloader($this->io, $this->composer->getConfig()),
    $githubToken,
    $httpBasicAuth,
    $pluginConfig['repositories'],
    $this->io,
);
```

### 2. Event Subscription
```php
public static function getSubscribedEvents()
{
    return [
        PluginEvents::PRE_FILE_DOWNLOAD => ['onDownload'],
    ];
}
```

### 3. Download Interception
```php
public function onDownload(PreFileDownloadEvent $preFileDownloadEvent): void
{
    $processedUrl = $preFileDownloadEvent->getProcessedUrl();
    
    // Debug logging
    $this->io->debug(sprintf('Processing URL: %s', $processedUrl));
    
    // Check if authentication is needed
    $needsAuth = $this->httpDownloader->isNeedAuthHeaders($processedUrl);
    
    if ($needsAuth) {
        // Add authentication headers
        $transportOptions = $this->httpDownloader->addAuthenticationHeaders($processedUrl, $options);
        $preFileDownloadEvent->setTransportOptions($transportOptions);
    }
}
```

### 4. Repository Matching
```php
public function isNeedAuthHeaders(string $url): bool
{
    return $this->isNeedGetReleaseUrl($url, ['/releases/download', '/repos']);
}

public function isNeedGetReleaseUrl(string $url, array $replacePatterns): bool
{
    // Parse URL to extract owner/repo
    $parts = explode('/', str_replace($replacePatterns, '', $urlParts['path']));
    
    // Match against configured repositories
    foreach ($this->repositories as $repository) {
        if (strtolower($repository['owner']) === strtolower($parts[1]) &&
            strtolower($repository['name']) === strtolower($parts[2])) {
            return true;
        }
    }
    
    return false;
}
```

### 5. Header Injection
```php
public function addAuthenticationHeaders(string $url, array $options, ?string $acceptType = null): array
{
    $headers = $options['http']['header'] ?? [];

    // Add GitHub token for GitHub URLs
    if ($this->githubToken && $this->isGitHubUrl($url)) {
        $headers[] = 'Authorization: token ' . $this->githubToken;
        $this->io->debug('Added GitHub token authorization header');
    }

    // Add HTTP basic auth
    if ($this->httpBasicAuth) {
        $auth = base64_encode($username . ':' . $password);
        $headers[] = 'Authorization: Basic ' . $auth;
        $this->io->debug('Added HTTP Basic auth header');
    }

    $options['http']['header'] = $headers;
    return $options;
}
```

## Configuration Examples

### Repository Configuration
```json
{
    "extra": {
        "composer-authenticated-plugin": {
            "repositories": [
                {
                    "owner": "MacPaw",
                    "name": "platform-shared-clients",
                    "url": "https://github.com/MacPaw/platform-shared-clients"
                }
            ]
        }
    }
}
```

### GitHub Token Authentication
```bash
# Configure GitHub token
composer config github-oauth.api.github.com YOUR_GITHUB_TOKEN
```

### HTTP Basic Authentication
```bash
# Configure HTTP basic auth
composer config http-basic.artifacts.company.com username password
```

### Environment Variables
```json
{
    "config": {
        "github-oauth": {
            "api.github.com": "%env(GITHUB_TOKEN)%"
        },
        "http-basic": {
            "artifacts.company.com": {
                "username": "%env(ARTIFACT_USERNAME)%",
                "password": "%env(ARTIFACT_PASSWORD)%"
            }
        }
    }
}
```

## Security Features

### 1. Repository Matching
- Only URLs matching configured repositories receive authentication
- Prevents accidental token exposure to unauthorized repositories
- Case-insensitive matching for owner and repository names

### 2. Token Security
- Tokens are retrieved from Composer configuration
- Support for environment variable substitution
- No hardcoded credentials in repository configuration

### 3. URL Detection
- Automatic GitHub URL detection for token injection
- Support for GitHub Enterprise hosts
- Secure header injection only for matching URLs

### 4. HTTPS Enforcement
- All requests use HTTPS
- No support for insecure HTTP connections
- Proper certificate validation

## Debug and Logging

### Debug Output
The plugin provides comprehensive debug logging when run with `-vvv`:

```
Processing URL: https://api.github.com/repos/MacPaw/platform-shared-clients/releases/assets/275933430
Needs auth headers: YES
Link supported for get asset download url: YES
Configured repositories: [{"owner":"MacPaw","name":"platform-shared-clients","url":"https://github.com/MacPaw/platform-shared-clients"}]
Added GitHub token authorization header
Final headers: ["Authorization: token ghp_xxx","Accept: application/octet-stream"]
```

### Debug Methods
- URL processing information
- Repository matching results
- Authentication header details
- Error conditions and fallbacks

## Error Handling

### 1. Missing Credentials
- Graceful fallback when credentials are not configured
- Clear error messages for authentication failures
- No breaking changes to existing workflows

### 2. Repository Configuration Errors
- Validation of required fields (owner, name)
- Warning messages for misconfigured repositories
- Helpful troubleshooting information

### 3. Network Issues
- Proper timeout handling
- Retry logic for transient failures
- Detailed error reporting

## Integration Points

### 1. Composer Configuration
- Uses existing `github-oauth` configuration
- Uses existing `http-basic` configuration
- Supports environment variable substitution

### 2. Event System
- Subscribes to `PreFileDownloadEvent`
- Integrates with Composer's download process
- Maintains compatibility with other plugins

### 3. HTTP Downloader
- Wraps existing HTTP downloader functionality
- Maintains all original downloader capabilities
- Adds authentication without breaking changes

## Usage Scenarios

### 1. Private GitHub Packages
- Configure repository in plugin's `extra` section
- Set up GitHub token with appropriate scopes
- Plugin automatically adds authentication to download requests

### 2. Enterprise Artifact Repositories
- Configure HTTP basic auth credentials
- Add repository to plugin configuration
- Automatic authentication for all requests to configured repositories

### 3. Mixed Authentication
- Support for both GitHub tokens and HTTP basic auth
- Repository-specific authentication based on configuration
- Flexible credential management

## Testing Strategy

### 1. Unit Tests
- Repository matching logic
- HTTP downloader authentication
- URL detection accuracy
- Header injection validation

### 2. Integration Tests
- End-to-end download process
- Authentication flow validation
- Error handling scenarios

### 3. Security Tests
- Token injection validation
- URL detection accuracy
- Header security verification

## Future Enhancements

### 1. Additional Authentication Methods
- OAuth 2.0 support
- API key authentication
- Custom header injection

### 2. Advanced Features
- Caching support
- Rate limiting
- Retry policies

### 3. Monitoring and Logging
- Request logging
- Performance metrics
- Error tracking

## Troubleshooting Guide

### Common Issues

1. **401 Authentication Errors**
   - Verify credentials are correctly configured
   - Check repository is listed in plugin configuration
   - Ensure owner/name match GitHub repository exactly

2. **Repository Not Found**
   - Ensure repository URL is correct
   - Verify authentication credentials
   - Check network connectivity

3. **Package Installation Failures**
   - Validate package exists in repository
   - Check package dependencies
   - Verify download URLs are accessible

### Debug Commands

```bash
# Check authentication configuration
composer config github-oauth
composer config http-basic

# Enable verbose output
composer install -vvv

# Test repository access
curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/repos/owner/repo
```

This plugin provides a seamless way to add authentication to Composer downloads while maintaining compatibility with existing workflows and security best practices. 
