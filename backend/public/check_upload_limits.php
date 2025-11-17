<?php
/**
 * PHP Upload Limits Checker
 * Access this file via: http://your-domain/check_upload_limits.php
 */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>PHP Upload Limits Checker</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .good { color: green; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .bad { color: red; font-weight: bold; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>PHP Upload Configuration Status</h1>
    
    <h2>Current PHP Settings</h2>
    <table>
        <tr>
            <th>Setting</th>
            <th>Current Value</th>
            <th>Recommended</th>
            <th>Status</th>
        </tr>
        <?php
        $settings = [
            'upload_max_filesize' => ['current' => ini_get('upload_max_filesize'), 'recommended' => '300M'],
            'post_max_size' => ['current' => ini_get('post_max_size'), 'recommended' => '350M'],
            'max_file_uploads' => ['current' => ini_get('max_file_uploads'), 'recommended' => '50'],
            'memory_limit' => ['current' => ini_get('memory_limit'), 'recommended' => '1024M'],
            'max_execution_time' => ['current' => ini_get('max_execution_time'), 'recommended' => '600'],
            'max_input_time' => ['current' => ini_get('max_input_time'), 'recommended' => '600'],
            'file_uploads' => ['current' => ini_get('file_uploads') ? 'On' : 'Off', 'recommended' => 'On']
        ];
        
        function parseSize($size) {
            $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
            $size = preg_replace('/[^0-9\.]/', '', $size);
            if ($unit) {
                return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
            } else {
                return round($size);
            }
        }
        
        foreach ($settings as $setting => $values) {
            $current = $values['current'];
            $recommended = $values['recommended'];
            
            // Determine status
            $status = 'good';
            $statusText = 'OK';
            
            if (in_array($setting, ['upload_max_filesize', 'post_max_size', 'memory_limit'])) {
                $currentBytes = parseSize($current);
                $recommendedBytes = parseSize($recommended);
                
                if ($currentBytes < $recommendedBytes) {
                    $status = 'bad';
                    $statusText = 'TOO LOW';
                } elseif ($currentBytes < $recommendedBytes * 1.2) {
                    $status = 'warning';
                    $statusText = 'MARGINAL';
                }
            } elseif (in_array($setting, ['max_execution_time', 'max_input_time', 'max_file_uploads'])) {
                if (intval($current) < intval($recommended)) {
                    $status = 'bad';
                    $statusText = 'TOO LOW';
                }
            } elseif ($setting === 'file_uploads' && $current !== 'On') {
                $status = 'bad';
                $statusText = 'DISABLED';
            }
            
            echo "<tr>";
            echo "<td>{$setting}</td>";
            echo "<td>{$current}</td>";
            echo "<td>{$recommended}</td>";
            echo "<td class='{$status}'>{$statusText}</td>";
            echo "</tr>";
        }
        ?>
    </table>
    
    <h2>Upload Test</h2>
    <p>Maximum theoretical upload size based on current settings:</p>
    <?php
    $uploadMax = parseSize(ini_get('upload_max_filesize'));
    $postMax = parseSize(ini_get('post_max_size'));
    $maxUpload = min($uploadMax, $postMax);
    
    echo "<p><strong>Effective upload limit: " . round($maxUpload / 1024 / 1024, 2) . " MB</strong></p>";
    
    if ($maxUpload >= parseSize('70M')) {
        echo "<p class='good'>✓ Should support 70MB+ files</p>";
    } else {
        echo "<p class='bad'>✗ Will NOT support 70MB+ files</p>";
    }
    ?>
    
    <h2>PHP Configuration File</h2>
    <p><strong>Loaded php.ini:</strong> <?php echo php_ini_loaded_file(); ?></p>
    <p><strong>Additional ini files:</strong> <?php echo php_ini_scanned_files() ?: 'None'; ?></p>
    
    <h2>Server Information</h2>
    <table>
        <tr><td>PHP Version</td><td><?php echo PHP_VERSION; ?></td></tr>
        <tr><td>Server Software</td><td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td></tr>
        <tr><td>Document Root</td><td><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?></td></tr>
    </table>
    
    <hr>
    <p><small>Generated on <?php echo date('Y-m-d H:i:s'); ?></small></p>
</body>
</html>


