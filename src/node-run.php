<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use Vendor\nvmPhp\nvmPhp;

// Get command from arguments or use default
$command = $argv[1] ?? 'node --version';

if (empty($command)) {
    echo "❌ No command specified.\n";
    echo "Usage: composer node-run \"your node command here\"\n";
    echo "Example: composer node-run \"npm --version\"\n";
    exit(1);
}

// Check if Node.js is installed
$tempInstance = new Vendor\nvmPhp\nvmPhp('v20.14.0');
$installedVersions = $tempInstance->listInstalledVersions();
if (empty($installedVersions)) {
    echo "❌ No Node.js installations found.\n";
    echo "💡 Use 'composer node-install v20.14.0' to install Node.js first.\n";
    exit(1);
}

echo "🔧 Executing Node.js command: {$command}\n";
echo "📦 Using installation: {$installedVersions[0]['version']}\n\n";

try {
    // Create instance and execute the Node.js command
    $instance = new nvmPhp();
    $instance->launchNodeTerminalWithCommand($command);
} catch (Exception $e) {
    echo "❌ Error executing command: " . $e->getMessage() . "\n";
    exit(1);
}

?>
