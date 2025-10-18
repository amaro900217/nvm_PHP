<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use Vendor\nvmPhp\nvmPhp;

echo "🚀 nvmPHP - Node.js Version Installer\n";
echo "====================================\n";

// Try to get version from command line arguments first
$version = null;
if (isset($argv[1]) && !empty($argv[1])) {
    $version = trim($argv[1]);
    echo "📋 Installing Node.js version: {$version}\n";
} else {
    echo "Enter Node.js version to install (e.g., v20.14.0): ";
    $version = trim(fgets(STDIN));

    if ($version === false || empty($version)) {
        echo "❌ No version specified. Usage:\n";
        echo "  composer install-node [version]\n";
        echo "Examples:\n";
        echo "  composer install-node v20.14.0\n";
        echo "  composer install-node v18.17.0\n";
        exit(1);
    }
}

// Validate version format
if (!preg_match('/^v\d+\.\d+\.\d+$/', $version)) {
    echo "❌ Invalid version format. Expected format: vXX.XX.XX\n";
    exit(1);
}

echo "📥 Installing Node.js {$version}...\n";

try {
    $installer = new nvmPhp($version);
    $installer->downloadAndInstall();
    echo "✅ Node.js {$version} installed successfully as dev dependency!\n";

    // Show installed versions
    echo "\n📦 Current installations:\n";
    nvmPhp::listInstalledNodeVersions();

} catch (Exception $e) {
    echo "❌ Installation failed: " . $e->getMessage() . "\n";
    exit(1);
}
