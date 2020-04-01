<?php

foreach (['cache', 'logs', 'modules', 'test_dir'] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir);
    }
}