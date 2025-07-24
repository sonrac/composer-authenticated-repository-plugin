# Composer Authenticated Repository Plugin - Implementation Overview

## Plugin Architecture

The plugin implements a custom repository type `composer-authenticated` that extends the standard Composer repository behavior with automatic authentication support. It uses Composer's capability system to register a new repository factory.

## Core Components

### 1. Main Plugin Class (`Plugin.php`)

**Purpose**: Entry point that implements `PluginInterface` and `Capable`

**Key Features**:
- Implements `PluginInterface` for basic plugin lifecycle
- Implements `Capable` to provide repository factory capability
- Registers `AuthenticatedRepositoryFactory` as a capability provider

**Methods**:
- `activate()`: Called when plugin is activated

### 2. Authenticated Repository (`AuthenticatedComposerRepository.php`)

**Purpose**: Wraps standard Composer repository with authentication

**Key Features**:
- Extends `ComposerRepository`
- Injects custom HTTP downloader with authentication
- Retrieves credentials from Composer configuration

**Methods**:
- `createAuthenticatedDownloader()`: Creates downloader with auth
- `getGitHubToken()`: Retrieves GitHub token from config
- `getHttpBasicAuth()`: Retrieves HTTP basic auth from config

### 3. Authenticated HTTP Downloader (`AuthenticatedHttpDownloader.php`)

**Purpose**: Wraps HTTP downloader with authentication headers

**Key Features**:
- Extends `HttpDownloader`
- Adds authentication headers to all requests
- Supports GitHub tokens and HTTP basic auth
- Detects GitHub URLs automatically

**Methods**:
- `get()`: Adds auth headers to GET requests
- `add()`: Adds auth headers to queued requests
- `copy()`: Adds auth headers to copy requests
- `addAuthenticationHeaders()`: Injects auth headers
- `isGitHubUrl()`: Detects GitHub URLs

## Authentication Flow

### 1. Repository Creation
```php
// User configures extra config
{
    "extra": {
        "composer-authenticated-plugin": {
            "repositories": [
                {
                    "owner": "my-org",
                    "name": "private-packages",
                    "url": "https://github.com/my-org/private-packages/releases/composer-repository/composer-repository.json"
                }
            ]
        }
    }
}

// Plugin creates authenticated repository
$repository = new AuthenticatedComposerRepository($config, $io, $composerConfig, $httpDownloader, $authConfig, $ownerName, $repoName);
```

### 2. Credential Retrieval
```php
// Extract GitHub token from Composer config
$githubTokens = $config->get('github-oauth') ?? [];
$token = $githubTokens['api.github.com'] ?? null;

// Extract HTTP basic auth from Composer config
$httpBasicAuth = $config->get('http-basic') ?? [];
$credentials = $httpBasicAuth['artifacts.company.com'] ?? null;
```

### 3. HTTP Downloader Wrapping
```php
// Create authenticated downloader
$authenticatedDownloader = new AuthenticatedHttpDownloader(
    $originalDownloader,
    $githubToken,
    $httpBasicAuth,
    $authConfig,
    $ownerName,
    $repoName,
);
```

### 4. Header Injection
```php
// Add GitHub token for GitHub URLs
if ($this->githubToken && $this->isGitHubUrl($url)) {
    $headers[] = 'Authorization: token ' . $this->githubToken;
}

// Add HTTP basic auth
if ($this->httpBasicAuth) {
    $auth = base64_encode($username . ':' . $password);
    $headers[] = 'Authorization: Basic ' . $auth;
}
```

## Configuration Examples

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

### 1. Token Security
- Tokens are retrieved from Composer configuration
- Support for environment variable substitution
- No hardcoded credentials in repository configuration

### 2. URL Detection
- Automatic GitHub URL detection for token injection
- Support for GitHub Enterprise hosts
- Secure header injection only for matching URLs

### 3. HTTPS Enforcement
- All requests use HTTPS
- No support for insecure HTTP connections
- Proper certificate validation

## Error Handling

### 1. Missing Credentials
- Graceful fallback when credentials are not configured
- Clear error messages for authentication failures
- No breaking changes to existing workflows

### 2. Network Issues
- Proper timeout handling
- Retry logic for transient failures
- Detailed error reporting

### 3. Configuration Errors
- Validation of repository configuration
- Clear error messages for misconfiguration
- Helpful troubleshooting information

## Integration Points

### 1. Composer Configuration
- Uses existing `github-oauth` configuration
- Uses existing `http-basic` configuration
- Supports environment variable substitution

### 2. Repository System
- Extends standard Composer repository behavior
- Compatible with all Composer repository features
- Transparent operation for end users

### 3. HTTP Downloader
- Wraps existing HTTP downloader functionality
- Maintains all original downloader capabilities
- Adds authentication without breaking changes

## Usage Scenarios

### 1. Private GitHub Packages

@TODO add description

### 2. Enterprise Artifact Repositories

@TODO add description

### 3. Mixed Authentication

@TODO add description

## Testing Strategy

### 1. Unit Tests
- Repository factory creation
- HTTP downloader authentication
- URL detection logic
- Header injection

### 2. Integration Tests
- End-to-end repository operations
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

1. **Authentication Errors**
   - Verify credentials are correctly configured
   - Check token scopes and permissions
   - Validate URL accessibility

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
composer install --verbose

# Test repository access
curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/repos/org/repo
```

This plugin provides a seamless way to use authenticated Composer repositories while maintaining compatibility with existing Composer workflows and security best practices. 
