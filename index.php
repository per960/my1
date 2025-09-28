
<?php
class AndroidDeviceInfo {
    
    public function getSerialNumber() {
        $methods = [
            'ro.serialno',
            'ro.boot.serialno',
            'persist.sys.serialnumber'
        ];
        
        foreach ($methods as $prop) {
            $serial = trim(shell_exec("getprop {$prop} 2>/dev/null"));
            if (!empty($serial) && $serial !== 'unknown') {
                return $serial;
            }
        }
        
        return $this->getSerialFromSystemFiles();
    }
    
    private function getSerialFromSystemFiles() {
        $files = [
            '/proc/cpuinfo',
            '/sys/class/android_usb/android0/iSerial',
            '/etc/serial'
        ];
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                if (preg_match('/Serial\s*:\s*([A-Za-z0-9]+)/', $content, $matches)) {
                    return $matches[1];
                }
            }
        }
        
        return 'Unknown';
    }
    
    public function getMacAddress() {
        $interfaces = ['wlan0', 'eth0', 'wifi0', 'tiwlan0'];
        
        foreach ($interfaces as $interface) {
            $path = "/sys/class/net/{$interface}/address";
            if (file_exists($path)) {
                $mac = trim(file_get_contents($path));
                if (!empty($mac) && $mac !== '00:00:00:00:00:00') {
                    return strtoupper($mac);
                }
            }
        }
        
        // Fallback to network commands
        $commands = [
            'ip addr show',
            'ifconfig',
            'netcfg'
        ];
        
        foreach ($commands as $command) {
            $output = shell_exec("{$command} 2>/dev/null");
            if (preg_match('/([a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2})/', $output, $matches)) {
                return strtoupper($matches[1]);
            }
        }
        
        return 'Unknown';
    }
    
    public function getAllDeviceInfo() {
        return [
            'serial_number' => $this->getSerialNumber(),
            'mac_address' => $this->getMacAddress(),
            'model' => trim(shell_exec('getprop ro.product.model 2>/dev/null')) ?: 'Unknown',
            'manufacturer' => trim(shell_exec('getprop ro.product.manufacturer 2>/dev/null')) ?: 'Unknown',
            'android_version' => trim(shell_exec('getprop ro.build.version.release 2>/dev/null')) ?: 'Unknown',
            'timestamp' => date('Y-m-d H:i:s'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ];
    }
}

// Usage
if (php_sapi_name() === 'cli' || defined('STDIN')) {
    // Running in CLI (like Termux)
    $deviceInfo = new AndroidDeviceInfo();
    $info = $deviceInfo->getAllDeviceInfo();
    
    foreach ($info as $key => $value) {
        echo ucfirst(str_replace('_', ' ', $key)) . ": " . $value . "\n";
    }
} else {
    // Running as web server
    header('Content-Type: application/json');
    $deviceInfo = new AndroidDeviceInfo();
    echo json_encode($deviceInfo->getAllDeviceInfo());
}
?>