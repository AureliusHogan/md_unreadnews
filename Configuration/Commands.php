<?php

/**
 * Register Symfony Console Commands (cli)
 */
return [
    'mdUnreadnews:cleanup' => [
        'class' => \Mediadreams\MdUnreadnews\Command\Cleanup::class,
    ],
    'mdUnreadnews:initializeData' => [
        'class' => \Mediadreams\MdUnreadnews\Command\InitializeData::class,
    ],
];
