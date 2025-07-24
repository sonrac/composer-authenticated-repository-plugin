#!/bin/bash

# Setup Composer Authenticated Repository Plugin Locally

set -e

echo "🔧 Setting up Composer Authenticated Repository Plugin locally..."

# Get the current directory (plugin directory)
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(pwd)"

echo "Plugin directory: $PLUGIN_DIR"
echo "Project directory: $PROJECT_DIR"

# Check if we're in a composer project
if [ ! -f "composer.json" ]; then
    echo "❌ No composer.json found. Please run this script from your project directory."
    exit 1
fi

# Create vendor directory for the plugin if it doesn't exist
mkdir -p vendor/sonrac

# Copy the plugin to vendor directory
echo "📦 Installing plugin to vendor directory..."
cp -r "$PLUGIN_DIR" "$PROJECT_DIR/vendor/sonrac/composer-authenticated-repository-plugin"

# Add the plugin as a path repository to composer.json
echo "⚙️  Adding plugin to composer.json..."

# Check if jq is available
if command -v jq &> /dev/null; then
    # Use jq to add the repository and require
    jq '.repositories += [{"type": "path", "url": "vendor/sonrac/composer-authenticated-repository-plugin"}]' composer.json > composer.json.tmp
    mv composer.json.tmp composer.json
    
    jq '.require += {"sonrac/composer-authenticated-repository-plugin": "*"}' composer.json > composer.json.tmp
    mv composer.json.tmp composer.json
    
    jq '.config.allow_plugins["sonrac/composer-authenticated-repository-plugin"] = true' composer.json > composer.json.tmp
    mv composer.json.tmp composer.json
else
    echo "⚠️  jq not found. Please manually add the following to your composer.json:"
    echo ""
    echo "In the repositories section:"
    echo '  {'
    echo '    "type": "path",'
    echo '    "url": "vendor/sonrac/composer-authenticated-repository-plugin"'
    echo '  }'
    echo ""
    echo "In the require section:"
    echo '  "sonrac/composer-authenticated-repository-plugin": "*"'
    echo ""
    echo "In the config section:"
    echo '  "allow-plugins": {'
    echo '    "sonrac/composer-authenticated-repository-plugin": true'
    echo '  }'
fi

# Install dependencies
echo "📥 Installing plugin dependencies..."
composer install

echo ""
echo "✅ Plugin setup complete!"
echo ""
echo "📝 You can now use the composer-authenticated repository type:"
echo '{'
echo '  "repositories": ['
echo '    {'
echo '      "type": "composer-authenticated",'
echo '      "url": "https://your-repo.com/composer-repository.json"'
echo '    }'
echo '  ]'
echo '}'
echo ""
echo "🔧 Configure authentication:"
echo "composer config github-oauth.your-repo.com YOUR_TOKEN"
echo "composer config http-basic.your-repo.com username password" 
