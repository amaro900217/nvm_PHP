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
        $this->version = $version;
        switch (PHP_OS_FAMILY) {
            case 'Windows':
                $this->extractDir  = __DIR__ . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . "node-$version-win-x64";
                $this->archiveFile = __DIR__ . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . "node-$version-win-x64.zip";
                break;
            case 'Darwin':
                $this->extractDir  = __DIR__ . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . "node-$version-darwin-x64";
                $this->archiveFile = __DIR__ . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . "node-$version-darwin-x64.tar.gz";
                break;
            case 'Linux':
                $this->extractDir  = __DIR__ . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . "node-$version-linux-x64";
                $this->archiveFile = __DIR__ . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . "node-$version-linux-x64.tar.xz";
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

    public function install(): void
    {
        if ($this->is64BitOS()) {
            $this->extractArchive();
            $this->checkAndSetPermissions();
            $this->verifyInstallation();
        } else {
            throw new \Exception("Unsupported architecture: " . php_uname('m') . "\n");
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
            $cmd = "start cmd.exe /k \"set PATH={$this->extractDir}\\node-v{$this->version}-win-x64;%PATH% && cd /d $currentDir\"";
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
        $cmd = '';

        if ($os === 'Windows') {
            $cmd = "start cmd.exe /k \"set PATH={$this->extractDir}\\node-{$this->version}-win-x64;%PATH% && cd /d $currentDir && $command\"";
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
                    throw new \Exception("Unsupported terminal emulator: $terminalApp");
            }
        }

        if ($os === 'Windows') {
            pclose(popen($cmd, "r"));
        } else {
            exec($cmd);
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
        $installer = new self('');  // Initialize without version for this method
        $compatibleVersions = $installer->checkAvailableNodeVersions();
        $osFamily = PHP_OS_FAMILY;
        $arch = $installer->is64BitOS() ? '64-bit' : '32-bit';
        echo "Available Node.js versions for $osFamily ($arch):\n";
        foreach ($compatibleVersions as $version) {
            echo "⮞ " . $version['version'] . "\n";
        }
    }

    public static function launchTerminalWithNodeCommand(string $command): void
    {
        $installer = new self('v20.14.0');  // Example version; adjust as needed
        $installer->launchNodeTerminalWithCommand($command);
    }
}

try {
    $installer = new nvmPhp('v20.14.0');
    $installer->install();
} catch (\Exception $e) {
    echo $e->getMessage();
}
