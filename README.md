# nvmPHP - Node.js Version Manager for PHP

A PHP library that allows you to manage portable Node.js installations directly from PHP, similar to nvm but written in PHP. It allows you to use Node.js in your project as a developer dependency for various tasks.

## 🚀 Features

- **🔄 Automatic Download**: Downloads any Node.js version automatically
- **🌐 Multi-platform Support**: Works on Linux, macOS, and Windows
- **⚡ Version Management**: List, install as dev-dependency, and manage multiple Node.js versions
- **🛡️ Safe Operations**: Confirmation prompts before destructive operations

## 📋 Requirements

- PHP 8.1 or higher
- cURL extension enabled
- Internet connection for downloading Node.js binaries

## 🛠️ Installation

```bash
git clone https://github.com/amaro900217/nvm_PHP.git
```

## 📖 Usage

### Command Line Interface

```bash
# Show available Node.js versions
composer node-available-versions

# List installed Node.js versions
composer node-installed-versions

# Interactive Node.js installation (prompts for version)
composer node-install

# Install specific Node.js version directly
composer node-install v20.14.0

# Execute Node.js commands from composer.json
composer node-run "your node command here"
### Execute Node.js Commands

The `composer node-run` command allows you to execute any Node.js command using the installed version:

```bash
# Basic Node.js commands
composer node-run "node --version"
composer node-run "npm --version"
composer node-run "npx --version"

# Package management
composer node-run "npm install lodash"
composer node-run "npm list"

# Run Node.js scripts
composer node-run "node script.js"

# NPX commands
composer node-run "npx create-react-app my-app"
composer node-run "npx typescript --version"
```

**Examples:**
```bash
# Check if Node.js is working
$ composer node-run "node --version"
🔧 Executing Node.js command: node --version
📦 Using installation: v25.0.0-linux-x64

🚀 Executing...
v25.0.0

# Install a package globally
$ composer node-run "npm install -g typescript"
🔧 Executing Node.js command: npm install -g typescript
📦 Using installation: v25.0.0-linux-x64

🚀 Executing...
...
```

**Note:** The command will use the first installed Node.js version found. Make sure Node.js is installed first using `composer node-install`.

### Interactive Installation

The `composer node-install` command provides flexible installation options:

```bash
# Interactive mode (prompts for version)
$ composer install-node
🚀 nvmPHP - Node.js Version Installer
====================================
Enter Node.js version to install (e.g., v20.14.0): v18.17.0
📥 Installing Node.js v18.17.0...
✅ Node.js v18.17.0 installed successfully as dev dependency!

# Direct mode (specify version as argument)
$ composer install-node v20.14.0
📋 Installing Node.js version: v20.14.0
📥 Installing Node.js v20.14.0...
✅ Node.js v20.14.0 installed successfully as dev dependency!
```

### PHP Code Examples

```php
<?php
require 'vendor/autoload.php';

use Vendor\nvmPhp\nvmPhp;

// Create a Node.js version manager instance
$nodeManager = new nvmPhp('v20.14.0');

// Automatically download and install Node.js
$nodeManager->downloadAndInstall();

// List all installed versions
$installations = $nodeManager->listInstalledVersions();

// Check if a specific version is installed
$isInstalled = $nodeManager->isVersionInstalled('v18.17.0');

// Launch terminal with Node.js environment
$nodeManager->launchNodeTerminal();

// Execute commands in Node.js environment
$nodeManager->launchNodeTerminalWithCommand('npm install -g typescript');

// Uninstall specific version
$nodeManager->uninstall('v18.17.0');

// Uninstall all versions
$nodeManager->uninstallAll();
```

### Static Method Examples

```php
<?php
// Show available Node.js versions without installation
nvmPhp::printNodeVersions();

// List installed versions
nvmPhp::listInstalledNodeVersions();

// Quick installation and terminal launch
nvmPhp::launchTerminalWithNodeCommand('node --version', 'v20.14.0');

// Uninstall specific version
nvmPhp::uninstallVersion('v18.17.0');

// Uninstall all versions
nvmPhp::uninstallAllVersions();
```

## 🎯 Practical Examples

### Development Workflow

```bash
# 1. Check available versions
composer node-available-versions

# 2. Install the latest LTS version
composer node-install v20.14.0

# 3. Verify installation
composer node-installed-versions

# 4. Launch terminal with Node.js
composer launch-terminal-with-and-test-command

# 5. In the terminal, you can now use:
# npm install -g typescript
# node --version
# npm --version
```

### Project Setup

```bash
# Install specific Node.js version for your project
composer node-install v18.17.0

# Launch terminal and create a new project
composer launch-terminal-with-and-test-command

# In the terminal:
# npx create-react-app my-app
# cd my-app
# npm start
```

### Version Management

```bash
# Install multiple versions
composer node-install v16.20.0
composer node-install v18.17.0
composer node-install v20.14.0

# List all installations
composer node-installed-versions

# Remove old versions
composer node-uninstall

# Clean up everything
composer node-uninstall
```

## 🔧 Methods Reference

### Instance Methods

| Method | Description |
|--------|-------------|
| `__construct(string $version)` | Create instance with specific Node.js version |
| `downloadAndInstall()` | Download and install Node.js automatically |
| `install()` | Install Node.js from local files |
| `uninstall(string $version)` | Remove specific Node.js version |
| `uninstallAll()` | Remove all Node.js installations |
| `listInstalledVersions()` | Get array of installed versions |
| `isVersionInstalled(string $version)` | Check if version is installed |
| `launchNodeTerminal()` | Launch terminal with Node.js in PATH |
| `launchNodeTerminalWithCommand(string $command)` | Launch terminal and execute command |

### Static Methods

| Method | Description |
|--------|-------------|
| `printNodeVersions()` | Display available Node.js versions |
| `listInstalledNodeVersions()` | Display installed versions |
| `launchTerminalWithNodeCommand(string $command, string $version)` | Quick install and command execution |
| `uninstallVersion(string $version)` | Remove specific version |
| `uninstallAllVersions()` | Remove all versions |
| `isVersionInstalled(string $version)` | Check if version is installed |

## 🌍 Supported Platforms

- **Linux** (x64 architecture)
- **macOS** (Intel x64, Apple Silicon ARM64 - requires testing)
- **Windows** (x64 architecture)

## 📁 Project Structure

```
nvmPHP/
├── composer.json           # Project configuration and scripts
├── README.md              # This documentation
├── src/                   # Source code
│   ├── nvmPhp.php         # Main nvmPHP class
│   └── install-node.php   # Interactive installation script
└── vendor/                # Composer dependencies
## 📁 Installation Directories

Node.js installations are stored in:
```
src/bin/node-{version}-{platform}-{arch}/
```

For example:
```
src/bin/node-v20.14.0-linux-x64/
src/bin/node-v18.17.0-darwin-x64/
src/bin/node-v20.10.0-win-x64/
```

- **Confirmation Prompts**: Asks for confirmation before uninstalling
- **Version Validation**: Validates Node.js version format (vXX.XX.XX)
- **Path Safety**: Secure handling of file paths and directories
- **Error Handling**: Comprehensive error messages and exception handling
- **🗑️ Complete Cleanup**: Removes all installation files and downloaded archives
- **Permission Management**: Handles file permission issues during operations

## 🚧 Current Limitations

- **Architecture Support**: Currently optimized for x64, ARM64 support needs testing
- **Interactive Prompts**: May require manual confirmation in some environments
- **Network Dependency**: Requires internet connection for automatic downloads

## 🤝 Contributing

This project is a PHP implementation of Node Version Manager functionality. Contributions for additional features, bug fixes, and platform support are welcome.

## 📄 License

This project is open source and available under the MIT License.
