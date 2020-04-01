<?php

foreach (['cache', 'logs', 'modules'] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir);
    }
}