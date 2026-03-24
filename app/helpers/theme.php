<?php
/**
 * Helper para cargar tema - fusiona defaults con storage/theme.json
 */

function getThemeConfig(): array
{
    $defaults = require MVC_CONFIG . '/theme.php';
    $storageFile = MVC_ROOT . '/storage/theme.json';

    if (file_exists($storageFile)) {
        $json = file_get_contents($storageFile);
        $overrides = json_decode($json, true);
        if (is_array($overrides)) {
            $defaults = array_replace_recursive($defaults, $overrides);
        }
    }

    return $defaults;
}

function saveThemeConfig(array $data): bool
{
    $storageDir = MVC_ROOT . '/storage';
    $storageFile = $storageDir . '/theme.json';

    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
    }

    $allowed = [
        'body' => ['gradient_start', 'gradient_end', 'gradient_angle'],
        'primary' => ['main', 'hover', 'text'],
        'links' => ['color', 'hover'],
        'typography' => ['font_size_base', 'font_family'],
        'borders' => ['radius', 'radius_sm', 'radius_lg'],
    ];

    $toSave = [];
    foreach ($allowed as $section => $keys) {
        $toSave[$section] = [];
        foreach ($keys as $key) {
            $val = $data[$section][$key] ?? '';
            if (is_string($val)) {
                $val = trim($val);
                $ok = false;
                if (preg_match('/^#[0-9A-Fa-f]{6}$/', $val) || preg_match('/^\d+deg$/', $val)) {
                    $ok = true;
                }
                if (in_array($key, ['font_size_base', 'font_family', 'radius', 'radius_sm', 'radius_lg']) && $val !== '') {
                    $ok = strlen($val) <= 80 && !preg_match('/[<>"\']/', $val);
                }
                if ($ok) {
                    $toSave[$section][$key] = $val;
                }
            }
        }
    }

    return file_put_contents($storageFile, json_encode($toSave, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}
