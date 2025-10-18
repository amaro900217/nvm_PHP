<?php

declare(strict_types=1);

namespace Vendor\nvmPhp;

class nvmPhp
{
    private string $extractDir;
    private string $archiveFile;
    private string $version;

    public function __construct(string $version = 'v20.14.0')
    {
        // Validate version format (should start with 'v' followed by numbers and dots)
        if (!preg_match('/^v\d+\.\d+\.\d+$/', $version)) {
            throw new \InvalidArgumentException("Invalid version format. Expected format: vXX.XX.XX");
        }

        $this->version = $version;
        switch (PHP_OS_FAMILY) {
            case 'Windows':
                $this->extractDir  = __DIR__ . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . "node-{$version}-win-x64";
                $this->archiveFile = __DIR__ . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . "node-{$version}-win-x64.zip";
                break;
            case 'Darwin':
                $this->extractDir  = __DIR__ . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . "node-{$version}-darwin-x64";
                $this->archiveFile = __DIR__ . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . "node-{$version}-darwin-x64.tar.gz";
                break;
            case 'Linux':
                $this->extractDir  = __DIR__ . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . "node-{$version}-linux-x64";
                $this->archiveFile = __DIR__ . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . "node-{$version}-linux-x64.tar.xz";
                break;
            default:
                throw new \Exception("Unsupported operating system: " . PHP_OS_FAMILY . "\n");
        }
    }

    private function is64BitOS(): bool {
        if (PHP_INT_SIZE === 8) {
            return true;
        }
    
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return (strpos(php_uname('m'), '64') !== false) || 
                   (getenv('PROCESSOR_ARCHITECTURE') === 'AMD64') ||
                   (getenv('PROCESSOR_ARCHITEW6432') === 'AMD64');
        }
    
        $machineType = php_uname('m');
        return ($machineType === 'x86_64' || $machineType === 'amd64');
    }

    public function downloadAndInstall(): void
    {
        echo "🚀 Starting automatic installation of Node.js {$this->version}\n";

        if (!$this->is64BitOS()) {
            throw new \Exception("Unsupported architecture: " . php_uname('m') . "\n");
        }

        $this->downloadArchive();
        $this->extractArchive();
        $this->checkAndSetPermissions();
        $this->verifyInstallation();

        echo "✅ Node.js {$this->version} installed successfully!\n";
    }

    private function downloadArchive(): void
    {
        echo "📥 Checking for existing installation files...\n";

        // Check if archive already exists
        if (file_exists($this->archiveFile)) {
            echo "📁 Archive file already exists: {$this->archiveFile}\n";
            return;
        }

        echo "🌐 Fetching download URL for Node.js {$this->version}...\n";

        // Get available versions and find download URL
        $compatibleVersions = $this->checkAvailableNodeVersions();
        $targetVersion = null;

        foreach ($compatibleVersions as $version) {
            if ($version['version'] === $this->version) {
                $targetVersion = $version;
                break;
            }
        }

        if ($targetVersion === null) {
            throw new \Exception("Version {$this->version} not found in available releases.");
        }

        // Build download URL based on OS and architecture
        $os = match (PHP_OS_FAMILY) {
            'Windows' => 'win',
            'Darwin' => 'darwin',
            'Linux' => 'linux',
            default => throw new \Exception("Unsupported operating system: " . PHP_OS_FAMILY)
        };

        $arch = $this->is64BitOS() ? 'x64' : 'x86';
        $filename = "node-{$this->version}-{$os}-{$arch}";
        $extension = match (PHP_OS_FAMILY) {
            'Windows' => '.zip',
            'Darwin' => '.tar.gz',
            'Linux' => '.tar.xz',
            default => throw new \Exception("Unsupported operating system: " . PHP_OS_FAMILY)
        };

        $downloadUrl = "https://nodejs.org/dist/{$this->version}/{$filename}{$extension}";

        echo "⬇️  Downloading from: {$downloadUrl}\n";

        // Download the file
        $this->downloadFile($downloadUrl, $this->archiveFile);

        if (!file_exists($this->archiveFile)) {
            throw new \Exception("Failed to download archive file.");
        }

        echo "✅ Download completed successfully!\n";
    }

    private function downloadFile(string $url, string $destination): void
    {
        $fileHandle = fopen($destination, 'w');

        if ($fileHandle === false) {
            throw new \Exception("Cannot create file: {$destination}");
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FILE, $fileHandle);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes timeout
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For compatibility

        // Set user agent to avoid blocking
        curl_setopt($ch, CURLOPT_USERAGENT, 'nvmPHP/1.0');

        $success = curl_exec($ch);

        if ($success === false) {
            $error = curl_error($ch);
            curl_close($ch);
            fclose($fileHandle);
            throw new \Exception("Download failed: {$error}");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fileHandle);

        if ($httpCode !== 200) {
            throw new \Exception("Download failed with HTTP code: {$httpCode}");
        }
    }

    private function extractArchive(): void
    {
        echo "Initiating extraction of Node.js...\n";

        if (!file_exists($this->extractDir)) {
            if (!mkdir($this->extractDir, 0777, true)) {
                throw new \Exception("Failed to create extraction directory at {$this->extractDir}");
            }
        }

        $files = scandir($this->extractDir);
        if (count($files) > 2) {
            echo "Directory already exists and is not empty. Skipping extraction.\n";
            return;
        }

        $fileExt = pathinfo($this->archiveFile, PATHINFO_EXTENSION);

        if ($fileExt === 'zip') {
            $this->extractZip();
        } elseif ($fileExt === 'gz' || $fileExt === 'xz') {
            $this->extractTar($fileExt);
        } else {
            throw new \Exception("Unsupported file type: {$this->archiveFile}");
        }
    }

    private function extractZip(): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($this->archiveFile) === TRUE) {
            $zip->extractTo($this->extractDir);
            $zip->close();
            echo "Unzip successful using ZipArchive.\n";
        } else {
            throw new \Exception("Failed to unzip {$this->archiveFile} using ZipArchive");
        }
    }

    private function extractTar(string $fileExt): void
    {
        $cmd = '';
        if ($fileExt === 'gz') {
            $cmd = "tar --strip-components=1 -xzf {$this->archiveFile} -C {$this->extractDir}";
        } elseif ($fileExt === 'xz') {
            $cmd = "tar --strip-components=1 -xJf {$this->archiveFile} -C {$this->extractDir}";
        }

        exec($cmd, $output, $return_var);
        if ($return_var === 0) {
            echo "Extraction successful using tar.\n";
        } else {
            throw new \Exception("Failed to extract {$this->archiveFile} using tar: " . implode("\n", $output));
        }
    }

    private function checkAndSetPermissions(): void
    {
        $nodeBinPath = $this->getNodeBinPath();
        $npmPath     = $this->getNpmPath();
        $npxPath     = $this->getNpxPath();

        if (!file_exists($nodeBinPath)) {
            throw new \Exception("Node binary not found at $nodeBinPath");
        }
        if (!file_exists($npmPath)) {
            throw new \Exception("npm-cli file not found at $npmPath");
        }
        if (!file_exists($npxPath)) {
            throw new \Exception("npx-cli file not found at $npxPath");
        }

        chmod($nodeBinPath, 0755);
        chmod($npmPath, 0755);
        chmod($npxPath, 0755);
    }

    private function getNodeBinPath(): string
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => $this->extractDir . DIRECTORY_SEPARATOR . 'node.exe',
            'Darwin'  => $this->extractDir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'node',
            'Linux'   => $this->extractDir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'node',
            default => throw new \Exception('Unsupported operating system')
        };
    }

    private function getNpmPath(): string
    {
        return $this->extractDir . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . 'npm' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'npm-cli.js';
    }

    private function getNpxPath(): string
    {
        return $this->extractDir . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . 'npm' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'npx-cli.js';
    }

    private function verifyInstallation(): void
    {
        $nodeBinPath = $this->getNodeBinPath();
        $npmPath = $this->getNpmPath();
        $npxPath = $this->getNpxPath();

        $this->executeCommand("$nodeBinPath -v", "Node.js");
        $this->executeCommand("$nodeBinPath $npmPath -v", "NPM");
        $this->executeCommand("$nodeBinPath $npxPath -v", "NPX");
    }

    private function checkAvailableNodeVersions(): array
    {
        $url = 'https://nodejs.org/dist/index.json';
        $versions = json_decode(file_get_contents($url), true);

        if ($versions === null) {
            throw new \Exception("Failed to fetch Node.js versions from the official repository.");
        }

        $osFamily = PHP_OS_FAMILY;
        $arch = $this->is64BitOS() ? 'x64' : 'x86';
        $os = match ($osFamily) {
            'Windows' => 'win',
            'Darwin'  => 'darwin',
            'Linux'   => 'linux',
            default   => throw new \Exception("Unsupported operating system: $osFamily")
        };

        $compatibleVersions = array_filter($versions, function ($version) use ($os, $arch) {
            return isset($version['files']) && in_array("$os-$arch", $version['files']);
        });

        if (empty($compatibleVersions)) {
            throw new \Exception("No compatible Node.js versions found for $osFamily $arch.");
        }

        return $compatibleVersions;
    }

    public function printAvailableNodeVersions(): void {
        $compatibleVersions = $this->checkAvailableNodeVersions();
        $osFamily = PHP_OS_FAMILY;
        $arch = $this->is64BitOS() ? '64-bit' : '32-bit';
        echo "Available Node.js versions for $osFamily ($arch):\n";
        foreach ($compatibleVersions as $version) {
            echo "⮞ " . $version['version'] . "\n";
        }
    }

    private function executeCommand(string $command, string $tool): void
    {
        $output = [];
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new \Exception("Failed to execute $tool. Please check the installation.\n" . implode("\n", $output));
        }
        echo "🗸 $tool version: " . implode("\n", $output) . "\n";
    }

    private function launchNodeTerminal(): void
    {
        $os = PHP_OS_FAMILY;
        $currentDir = getcwd();
        $cmd = '';

        if ($os === 'Windows') {
            $cmd = "start cmd.exe /k \"set PATH={$this->extractDir};%PATH% && cd /d $currentDir\"";
        } elseif ($os === 'Darwin') {
            $cmd = "osascript -e 'tell application \"Terminal\" to do script \"export PATH={$this->extractDir}/bin:\$PATH; cd $currentDir;\"'";
        } else {
            $terminalApp = $this->getTerminalApp();
            switch ($terminalApp) {
                case 'gnome-terminal':
                    $cmd = "gnome-terminal -- bash -c 'export PATH=\"{$this->extractDir}/bin:\$PATH\" && cd \"$currentDir\" && exec \$SHELL'";
                    break;
                case 'kgx':
                    $cmd = "kgx -- bash -c 'export PATH=\"{$this->extractDir}/bin:\$PATH\" && cd \"$currentDir\" && exec \$SHELL'";
                    break;
                case 'xterm':
                    $cmd = "xterm -hold -e 'bash -c \"export PATH=\\\"{$this->extractDir}/bin:\\\$PATH\\\" && cd \\\"$currentDir\\\" && exec \$SHELL\"'";
                    break;
                case 'konsole':
                    $cmd = "konsole --noclose -e 'bash -c \"export PATH=\\\"{$this->extractDir}/bin:\\\$PATH\\\" && cd \\\"$currentDir\\\" && exec \$SHELL\"'";
                    break;
                case 'xfce4-terminal':
                    $cmd = "xfce4-terminal --hold -e 'bash -c \"export PATH=\\\"{$this->extractDir}/bin:\\\$PATH\\\" && cd \\\"$currentDir\\\" && exec \$SHELL\"'";
                    break;
                default:
                    throw new \Exception("Unsupported terminal emulator: $terminalApp");
            }
        }

        if ($os === 'Windows') {
            pclose(popen($cmd, "r"));
        } else {
            exec($cmd);
        }
    }

    public function launchNodeTerminalWithCommand(string $command): void
    {
        $os = PHP_OS_FAMILY;
        $currentDir = getcwd();

        // Check if we're in a non-graphical environment or CI/CD
        if ($this->shouldUseDirectExecution()) {
            $this->executeCommandDirectly($command);
            return;
        }

        $cmd = '';

        if ($os === 'Windows') {
            $cmd = "start cmd.exe /k \"set PATH={$this->extractDir};%PATH% && cd /d $currentDir && $command\"";
        } elseif ($os === 'Darwin') {
            $cmd = "osascript -e 'tell application \"Terminal\" to do script \"export PATH={$this->extractDir}/bin:\$PATH; cd $currentDir; $command;\"'";
        } else {
            $terminalApp = $this->getTerminalApp();
            switch ($terminalApp) {
                case 'gnome-terminal':
                    $cmd = "gnome-terminal -- bash -c 'export PATH=\"{$this->extractDir}/bin:\$PATH\" && cd \"$currentDir\" && $command && exec \$SHELL'";
                    break;
                case 'kgx':
                    $cmd = "kgx -- bash -c 'export PATH=\"{$this->extractDir}/bin:\$PATH\" && cd \"$currentDir\" && $command && exec \$SHELL'";
                    break;
                case 'xterm':
                    $cmd = "xterm -hold -e 'bash -c \"export PATH=\\\"{$this->extractDir}/bin:\\\$PATH\\\" && cd \\\"$currentDir\\\" && $command && exec \$SHELL\"'";
                    break;
                case 'konsole':
                    $cmd = "konsole --noclose -e 'bash -c \"export PATH=\\\"{$this->extractDir}/bin:\\\$PATH\\\" && cd \\\"$currentDir\\\" && $command && exec \$SHELL\"'";
                    break;
                case 'xfce4-terminal':
                    $cmd = "xfce4-terminal --hold -e 'bash -c \"export PATH=\\\"{$this->extractDir}/bin:\\\$PATH\\\" && cd \\\"$currentDir\\\" && $command && exec \$SHELL\"'";
                    break;
                default:
                    // Fallback to direct execution if terminal not supported
                    $this->executeCommandDirectly($command);
                    return;
            }
        }

        if ($os === 'Windows') {
            pclose(popen($cmd, "r"));
        } else {
            exec($cmd);
        }
    }

    private function shouldUseDirectExecution(): bool
    {
        // Check for common non-graphical environments
        $nonGraphicalEnvs = [
            'vscode',
            'cursor',
            'code',
            'truecolor',
            'CI', // GitHub Actions, Travis CI, etc.
            'GITHUB_ACTIONS',
            'TRAVIS',
            'CIRCLECI',
            'JENKINS_HOME'
        ];

        foreach ($nonGraphicalEnvs as $env) {
            if (isset($_SERVER['TERM_PROGRAM']) && stripos($_SERVER['TERM_PROGRAM'], $env) !== false) {
                return true;
            }
            if (isset($_ENV[$env]) || getenv($env) !== false) {
                return true;
            }
        }

        // Check if we're in a headless environment
        if (!isset($_SERVER['DISPLAY']) || empty($_SERVER['DISPLAY'])) {
            return true;
        }

        return false;
    }

    private function executeCommandDirectly(string $command): void
    {
        // Set up the PATH to include Node.js bin directory
        $nodeBinPath = $this->extractDir . DIRECTORY_SEPARATOR . 'bin';
        $currentPath = getenv('PATH');
        $newPath = $nodeBinPath . PATH_SEPARATOR . $currentPath;

        // Execute the command directly
        putenv("PATH={$newPath}");

        echo "🔧 Executing command directly: {$command}\n";
        echo "----------------------------------------\n";

        $output = [];
        $returnVar = 0;

        // Use proc_open for better control
        $descriptorspec = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w']  // stderr
        ];

        $process = proc_open($command, $descriptorspec, $pipes);

        if (is_resource($process)) {
            // Read stdout
            if (isset($pipes[1])) {
                while (!feof($pipes[1])) {
                    $line = fgets($pipes[1]);
                    if ($line !== false) {
                        echo $line;
                    }
                }
                fclose($pipes[1]);
            }

            // Read stderr
            if (isset($pipes[2])) {
                while (!feof($pipes[2])) {
                    $line = fgets($pipes[2]);
                    if ($line !== false) {
                        echo $line;
                    }
                }
                fclose($pipes[2]);
            }

            $returnVar = proc_close($process);

            if ($returnVar !== 0) {
                echo "\n⚠️  Command exited with code: {$returnVar}\n";
            } else {
                echo "\n✅ Command executed successfully!\n";
            }
        } else {
            throw new \Exception("Failed to execute command directly");
        }
    }

    private function getTerminalApp(): string
    {
        $terminalVars = [
            'TERM_PROGRAM',
            'COLORTERM',
            'TERM',
            'XTERM_VERSION'
        ];

        foreach ($terminalVars as $var) {
            if (isset($_SERVER[$var])) {
                return $_SERVER[$var];
            }
        }

        $output = [];
        exec('ps -o comm= -p ' . getmypid(), $output);
        return $output[0] ?? 'Unknown';
    }

    public static function printNodeVersions(): void
    {
        // Create a temporary instance to get available versions without installing
        $tempInstance = new self(); // Use default version for validation only
        $compatibleVersions = $tempInstance->checkAvailableNodeVersions();
        $osFamily = PHP_OS_FAMILY;
        $arch = $tempInstance->is64BitOS() ? '64-bit' : '32-bit';
        echo "Available Node.js versions for $osFamily ($arch):\n";
        foreach ($compatibleVersions as $version) {
            echo "⮞ " . $version['version'] . "\n";
        }
    }

    public function listInstalledVersions(): array
    {
        $binDir = __DIR__ . DIRECTORY_SEPARATOR . 'bin';
        $installedVersions = [];

        if (!is_dir($binDir)) {
            return $installedVersions;
        }

        $dirs = scandir($binDir);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..' || !is_dir($binDir . DIRECTORY_SEPARATOR . $dir)) {
                continue;
            }

            // Check if this looks like a Node.js installation directory
            if (preg_match('/^node-v\d+\.\d+\.\d+/', $dir)) {
                $version = str_replace('node-', '', $dir);
                $nodePath = $binDir . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'node';

                if (file_exists($nodePath)) {
                    $installedVersions[] = [
                        'version' => $version,
                        'path' => $binDir . DIRECTORY_SEPARATOR . $dir,
                        'installed' => true
                    ];
                }
            }
        }

        return $installedVersions;
    }

    public static function listInstalledNodeVersions(): void
    {
        $tempInstance = new self('v20.14.0'); // Use default version for validation only
        $installedVersions = $tempInstance->listInstalledVersions();

        if (empty($installedVersions)) {
            echo "No Node.js installations found.\n";
            return;
        }

        echo "Installed Node.js versions:\n";
        foreach ($installedVersions as $version) {
            echo "⮞ {$version['version']} (installed at: {$version['path']})\n";
        }
    }

    public function uninstall(string $version): void
    {
        $installations = $this->listInstalledVersions();
        $targetInstallation = null;

        // Find the specific version to uninstall
        foreach ($installations as $installation) {
            // Compare both full version and short version
            if ($installation['version'] === $version ||
                $installation['version'] === $version . '-linux-x64' ||
                strpos($installation['version'], $version) === 0) {
                $targetInstallation = $installation;
                break;
            }
        }

        if ($targetInstallation === null) {
            throw new \Exception("Node.js version {$version} is not installed.");
        }

        echo "🗑️  Uninstalling Node.js {$version}...\n";
        echo "📁 Installation path: {$targetInstallation['path']}\n";

        // Confirm before deletion (in interactive mode)
        if (php_sapi_name() === 'cli' && function_exists('readline') && is_resource(STDIN)) {
            echo "⚠️  This will permanently delete the installation. Continue? (y/N): ";
            $confirmation = readline();
            if (strtolower($confirmation) !== 'y' && strtolower($confirmation) !== 'yes') {
                echo "❌ Uninstallation cancelled.\n";
                return;
            }
        }

        // Remove the installation directory
        $this->removeDirectory($targetInstallation['path']);

        echo "✅ Node.js {$version} has been successfully uninstalled!\n";
    }

    public function uninstallAll(): void
    {
        $installations = $this->listInstalledVersions();

        if (empty($installations)) {
            echo "📭 No Node.js installations found to uninstall.\n";
            return;
        }

        echo "🗑️  Uninstalling all Node.js installations...\n";

        // Confirm before deletion (in interactive mode)
        if (php_sapi_name() === 'cli' && function_exists('readline') && is_resource(STDIN)) {
            echo "⚠️  This will permanently delete ALL installations and downloaded files. Continue? (y/N): ";
            $confirmation = readline();
            if ($confirmation === false || (strtolower($confirmation) !== 'y' && strtolower($confirmation) !== 'yes')) {
                echo "❌ Uninstallation cancelled.\n";
                return;
            }
        }

        foreach ($installations as $installation) {
            echo "📁 Removing: {$installation['version']}\n";
            $this->removeDirectory($installation['path']);
        }

        // Clean up downloaded archive files as well
        $this->cleanupArchiveFiles();

        echo "✅ All Node.js installations have been successfully uninstalled!\n";
    }

    private function cleanupArchiveFiles(): void
    {
        $binDir = __DIR__ . DIRECTORY_SEPARATOR . 'bin';

        if (!is_dir($binDir)) {
            return;
        }

        $files = scandir($binDir);
        $archiveExtensions = ['tar.xz', 'tar.gz', 'zip'];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $binDir . DIRECTORY_SEPARATOR . $file;

            // Check if file exists and is a regular file
            if (!is_file($filePath)) {
                continue;
            }

            // Check filename patterns for archive files
            foreach ($archiveExtensions as $ext) {
                if (str_ends_with(strtolower($file), '.' . $ext)) {
                    echo "🗑️  Removing archive file: {$file}\n";

                    try {
                        if (!unlink($filePath)) {
                            // Try with chmod if unlink fails
                            if (PHP_OS_FAMILY === 'Linux') {
                                exec("chmod 644 {$filePath}");
                                unlink($filePath);
                            }
                        }
                    } catch (\Exception $e) {
                        echo "⚠️  Warning: Could not remove archive file {$file}: {$e->getMessage()}\n";
                    }
                    break; // Found and processed, move to next file
                }
            }
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            throw new \Exception("Directory does not exist: {$directory}");
        }

        echo "🗑️  Removing directory: {$directory}\n";

        // Use recursive removal with better error handling
        $this->removeDirectoryRecursive($directory);

        // Final cleanup - ensure directory is completely removed
        if (is_dir($directory)) {
            // Try with different permissions if needed
            if (!rmdir($directory)) {
                // On Linux, try to change permissions first
                if (PHP_OS_FAMILY === 'Linux') {
                    exec("chmod -R 755 {$directory}");
                    exec("rm -rf {$directory}");
                }
            }
        }
    }

    private function removeDirectoryRecursive(string $directory): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();

            try {
                if ($file->isDir()) {
                    // Ensure directory is writable before removal
                    if (!rmdir($filePath)) {
                        // Try with chmod if rmdir fails
                        if (PHP_OS_FAMILY === 'Linux') {
                            exec("chmod 755 {$filePath}");
                            rmdir($filePath);
                        }
                    }
                } else {
                    // Ensure file is writable before removal
                    if (!unlink($filePath)) {
                        // Try with chmod if unlink fails
                        if (PHP_OS_FAMILY === 'Linux') {
                            exec("chmod 644 {$filePath}");
                            unlink($filePath);
                        }
                    }
                }
            } catch (\Exception $e) {
                echo "⚠️  Warning: Could not remove {$filePath}: {$e->getMessage()}\n";
            }
        }
    }

    public function isVersionInstalled(string $version): bool
    {
        $installations = $this->listInstalledVersions();

        foreach ($installations as $installation) {
            // Compare both full version and short version
            if ($installation['version'] === $version ||
                $installation['version'] === $version . '-linux-x64' ||
                strpos($installation['version'], $version) === 0) {
                return true;
            }
        }

        return false;
    }

    public static function uninstallVersion(string $version): void
    {
        $tempInstance = new self();
        $tempInstance->uninstall($version);
    }

    public static function uninstallAllVersions(): void
    {
        $tempInstance = new self();
        $tempInstance->uninstallAll();
    }
}
