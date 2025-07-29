# Troubleshooting Guide

## Common Issues

### 1. Plugin Not Found

**Error:** `Could not find package sonrac/composer-authenticated-repository-plugin`

**Solution:** The plugin needs to be published to Packagist or installed from a local path.

### 2. 401 Authentication Errors

**Error:** `[401] https://api.github.com/repos/owner/repo/releases/assets/123456`

**Solutions:**
1. **Check GitHub token configuration:**
   ```bash
   composer config github-oauth
   ```

2. **Verify repository is configured in plugin:**
   ```json
   {
       "extra": {
           "composer-authenticated-plugin": {
               "repositories": [
                   {
                       "owner": "owner",
                       "name": "repo",
                       "url": "https://github.com/owner/repo"
                   }
               ]
           }
       }
   }
   ```

3. **Enable debug mode to see what's happening:**
   ```bash
   composer install -vvv
   ```

### 3. Authentication Headers Not Added

**Error:** Debug output shows "Needs auth headers: NO"

**Cause:** The URL doesn't match any configured repositories

**Solutions:**
1. **Add the repository to plugin configuration** with correct `owner` and `name` fields
2. **Check case sensitivity** - the plugin matches case-insensitive but verify the spelling
3. **Verify URL format** - the plugin expects GitHub URLs in specific formats

### 4. Permission Issues

**Error:** `Permission denied` when installing

**Solution:** Check file permissions and ensure you have write access to the vendor directory.

### 5. Autoload Issues

**Error:** `Class not found` errors

**Solution:** Regenerate autoload files:

```bash
composer dump-autoload
```

### 6. Version Conflicts

**Error:** Version constraint conflicts

**Solution:** Check Composer version compatibility and update if needed:

```bash
composer self-update
```

### 7. Repository Configuration Errors

**Error:** Warning about missing `owner` or `name` parameters

**Solution:** Ensure all repositories in the plugin configuration have both `owner` and `name` fields:

```json
{
    "extra": {
        "composer-authenticated-plugin": {
            "repositories": [
                {
                    "owner": "owner-name",
                    "name": "repo-name",
                    "url": "https://github.com/owner-name/repo-name"
                }
            ]
        }
    }
}
```

## Debug Steps

### 1. Enable Verbose Output

```bash
composer install -vvv
```

Look for debug messages like:
- `Processing URL: ...`
- `Needs auth headers: YES/NO`
- `Configured repositories: ...`
- `Added GitHub token authorization header`

### 2. Check Authentication Configuration

```bash
# Check GitHub token configuration
composer config github-oauth

# Check HTTP basic auth configuration
composer config http-basic
```

### 3. Test Repository Access

```bash
# Test with curl (replace with your actual token and repository)
curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/repos/owner/repo
```

### 4. Verify Plugin Configuration

Check that your `composer.json` has:
- Plugin in `require` section
- `allow-plugins` configuration
- Correct `extra.composer-authenticated-plugin.repositories` configuration

## Plugin Status

The plugin works with:
- Composer 2.x
- PHP 8.0+
- GitHub repositories (public and private)
- HTTP basic authentication
- Standard Composer workflows

## Getting Help

If you're still experiencing issues:

1. **Check the debug logs:** Run `composer install -vvv` and look for plugin debug output
2. **Verify your setup:** Ensure all prerequisites are met
3. **Test with a minimal configuration:** Start with a simple setup and add complexity
4. **Check for conflicts:** Ensure no other plugins or configurations are interfering
5. **Review the GitHub authentication troubleshooting guide:** See `GITHUB_AUTH_TROUBLESHOOTING.md` for detailed GitHub-specific issues

## Common Debug Output

### Successful Authentication
```
Processing URL: https://api.github.com/repos/owner/repo/releases/assets/123456
Needs auth headers: YES
Link supported for get asset download url: YES
Configured repositories: [{"owner":"owner","name":"repo","url":"https://github.com/owner/repo"}]
Added GitHub token authorization header
Final headers: ["Authorization: token ghp_xxx","Accept: application/octet-stream"]
```

### Repository Not Configured
```
Processing URL: https://api.github.com/repos/owner/repo/releases/assets/123456
Needs auth headers: NO
Configured repositories: []
URL does not need auth headers - this might be the issue!
```

### Missing Repository Configuration
```
You must set `owner` & `name` params for repository config. Fix config for plugin composer-authenticated-plugin
```

If you continue to have issues, please:
1. Check the plugin's GitHub repository for known issues
2. Verify your Composer and PHP versions
3. Review the debug output for specific error messages
4. Try the alternative solutions above 
