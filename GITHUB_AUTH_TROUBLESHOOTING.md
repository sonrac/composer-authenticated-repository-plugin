# GitHub Authentication Troubleshooting Guide

## Problem
You're getting the following error when trying to download GitHub release assets:

```
Could not fetch https://api.github.com/repos/MacPaw/platform-shared-clients/releases/assets/275933687, please review your configured GitHub OAuth token or enter a new one to access private repos
```

## Root Cause
The issue occurs when Composer tries to access GitHub release assets but doesn't have proper authentication configured. This can happen with:

1. **Private repositories** - Require authentication even for public assets
2. **Rate limiting** - GitHub API has rate limits for unauthenticated requests
3. **Asset access permissions** - Some assets require specific permissions
4. **Repository not configured** - The plugin only adds authentication for repositories listed in its configuration

## Solutions

### 1. Configure GitHub Token

#### Option A: Environment Variable (Recommended)
```bash
export GITHUB_TOKEN=your_github_token_here
```

#### Option B: Composer Configuration File
Create or edit `~/.composer/auth.json`:
```json
{
    "github-oauth": {
        "api.github.com": "your_github_token_here"
    }
}
```

#### Option C: Project-specific Configuration
Add to your `composer.json`:
```json
{
    "config": {
        "github-oauth": {
            "api.github.com": "your_github_token_here"
        }
    }
}
```

### 2. Configure Plugin Repository Matching

**Important**: The plugin only adds authentication headers for repositories that are explicitly configured in the plugin's `extra` section.

Add the repository to your `composer.json`:

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

**Note**: The `owner` and `name` fields must match the GitHub repository owner and name exactly (case-insensitive).

### 3. Create GitHub Token

#### For Public Repositories Only:
1. Go to https://github.com/settings/tokens/new?scopes=&description=Composer+Authentication
2. Give it a descriptive name (e.g., "Composer Authentication")
3. No scopes needed for public repos
4. Click "Generate token"
5. Copy the token and use it in your configuration

#### For Private Repositories:
1. Go to https://github.com/settings/tokens/new?scopes=repo&description=Composer+Authentication
2. Give it a descriptive name (e.g., "Composer Authentication")
3. Select the `repo` scope (gives read/write access to private repos)
4. Click "Generate token"
5. Copy the token and use it in your configuration

### 4. Test Your Configuration

Enable debug mode to see what the plugin is doing:

```bash
composer install -vvv
```

Look for debug output like:
```
Processing URL: https://api.github.com/repos/MacPaw/platform-shared-clients/releases/assets/275933430
Needs auth headers: YES
Link supported for get asset download url: YES
Configured repositories: [{"owner":"MacPaw","name":"platform-shared-clients","url":"https://github.com/MacPaw/platform-shared-clients"}]
Added GitHub token authorization header
```

## Common Issues and Solutions

### Issue: "Not Found" Error
**Cause**: Repository doesn't exist or is private
**Solution**: Ensure you have access to the repository and your token has the correct permissions

### Issue: "Bad Credentials" Error
**Cause**: Invalid or expired token
**Solution**: Generate a new GitHub token and update your configuration

### Issue: "Rate Limit Exceeded" Error
**Cause**: Too many API requests
**Solution**: Use authentication (even for public repos) to get higher rate limits

### Issue: "Forbidden" Error
**Cause**: Token lacks required permissions
**Solution**: Ensure your token has the `repo` scope for private repositories

### Issue: Authentication Headers Not Added
**Cause**: Repository not configured in plugin's `extra` section
**Solution**: Add the repository to the plugin configuration with correct `owner` and `name` fields

### Issue: Debug Shows "Needs auth headers: NO"
**Cause**: URL doesn't match configured repositories
**Solution**: 
1. Check that the repository is listed in `extra.composer-authenticated-plugin.repositories`
2. Verify the `owner` and `name` match the GitHub repository exactly
3. Check the debug output to see what repositories are configured

### Issue: Asset Download Fails with Redirect
**Cause**: GitHub release assets return redirect URLs that need to be followed
**Solution**: The plugin automatically handles redirects. If you're still having issues:
1. Check that your token has the correct permissions
2. Verify the asset exists and is accessible
3. Ensure the repository is configured in the plugin

## Security Best Practices

1. **Use environment variables** instead of hardcoding tokens in files
2. **Use minimal scopes** - only grant the permissions you need
3. **Rotate tokens regularly** - GitHub tokens don't expire but should be rotated
4. **Use different tokens** for different environments (dev, staging, production)
5. **Configure repository matching** - only add authentication for repositories you trust

## Debugging Steps

1. **Check token validity**:
   ```bash
   curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/user
   ```

2. **Check repository access**:
   ```bash
   curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/repos/MacPaw/platform-shared-clients
   ```

3. **Check asset access**:
   ```bash
   curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/repos/MacPaw/platform-shared-clients/releases/assets/275933687
   ```

4. **Enable debug mode**:
   ```bash
   composer install -vvv
   ```

5. **Check plugin configuration**:
   Verify your `composer.json` has the correct `extra.composer-authenticated-plugin.repositories` configuration.

6. **Test repository matching**:
   Look for debug output showing whether the URL matches configured repositories.

## Plugin Updates

The plugin has been updated to:
- **Use PreFileDownloadEvent** to intercept downloads and add authentication headers
- **Require repository configuration** in the plugin's `extra` section
- **Provide comprehensive debug logging** to help troubleshoot issues
- **Match repositories by owner and name** to ensure authentication is only added for configured repositories
- **Support both GitHub tokens and HTTP basic auth** with proper header injection
- **Handle GitHub asset downloads** with automatic redirect following
- **Maintain compatibility** with existing Composer workflows

## Still Having Issues?

If you're still experiencing problems:

1. **Check the debug output** when running `composer install -vvv`
2. **Verify repository configuration** in the plugin's `extra` section
3. **Check GitHub repository permissions** and visibility
4. **Verify your token has the correct scopes**
5. **Test with the provided curl commands** to isolate the issue
6. **Review the plugin's error handling** in the debug logs 