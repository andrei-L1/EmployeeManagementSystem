<?php
require __DIR__ . '/../vendor/autoload.php';

$options = array(
    'cluster' => 'ap1',
    'useTLS' => true
);

$pusher = new Pusher\Pusher(
    '10ac21fee8c24f99545b',
    'c376cd3f8f03180a91c8',
    '1995227',
    $options
);

return $pusher; 