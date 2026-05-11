<?php
$configPath = 'data/config.php';
if (file_exists($configPath)) {
    $config = include $configPath;
    if (isset($config['tabList'])) {
        if (!in_array('VolontarioDipendente', $config['tabList'])) {
            // Find index of 'Team' or 'Document' or end of array
            $pos = array_search('Team', $config['tabList']);
            if ($pos === false) {
                $pos = count($config['tabList']) - 1;
            }
            array_splice($config['tabList'], $pos + 1, 0, 'VolontarioDipendente');
            
            // Re-index array
            $config['tabList'] = array_values($config['tabList']);
            
            $content = "<?php\nreturn " . var_export($config, true) . ";\n";
            file_put_contents($configPath, $content);
            echo "Added VolontarioDipendente to tabList.\n";
        } else {
            echo "Already in tabList.\n";
        }
    }
}
