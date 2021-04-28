<?php
defined('TYPO3_MODE') || die('Access denied.');

//use TYPO3\CMS\Core\Log\LogLevel;
//use TYPO3\CMS\Core\Log\Writer\FileWriter;

call_user_func(
    function () {

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
            'Mediadreams.MdUnreadnews',
            'Unread',
            [
                'Unreadnews' => 'list, isUnread, allUnreadCount, categoryCount, removeUnread'
            ],
            // non-cacheable actions
            [
                'Unreadnews' => 'list, isUnread, allUnreadCount, categoryCount, removeUnread'
            ]
        );

        $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);

        $iconRegistry->registerIcon(
            'md_unreadnews-plugin-unread',
            \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
            ['source' => 'EXT:md_unreadnews/Resources/Public/Icons/user_plugin_unread.svg']
        );

        // hook into saving process of news records
        $GLOBALS['TYPO3_CONF_VARS']
            ['SC_OPTIONS']
            ['t3lib/class.t3lib_tcemain.php']
            ['processDatamapClass']
            ['md_unreadnews'] = \Mediadreams\MdUnreadnews\Hooks\TCEmainHook::class;

        $GLOBALS['TYPO3_CONF_VARS']
            ['SC_OPTIONS']
            ['t3lib/class.t3lib_tcemain.php']
            ['processCmdmapClass']
            ['md_unreadnews_delete'] = \Mediadreams\MdUnreadnews\Hooks\TCEmainHook::class;


        // signal hook to create unread data for frontend news
        $signalSlotDispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);

// slot for ext:md_newsfrontend
        $signalSlotDispatcher->connect(
            \Mediadreams\MdNewsfrontend\Controller\NewsController::class,
            'createActionAfterPersist',
            \Mediadreams\MdUnreadnews\Slot\Unread::class,
            'addUnread'
        );

// slot for ext:md_newsfrontend
        $signalSlotDispatcher->connect(
            \Mediadreams\MdNewsfrontend\Controller\NewsController::class,
            'updateActionBeforeSave',
            \Mediadreams\MdUnreadnews\Slot\Unread::class,
            'updateUnread'
        );

// slot for ext:md_newsfrontend
        $signalSlotDispatcher->connect(
            \Mediadreams\MdNewsfrontend\Controller\NewsController::class,
            'deleteActionBeforeDelete',
            \Mediadreams\MdUnreadnews\Slot\Unread::class,
            'removeUnread'
        );

//        $GLOBALS['TYPO3_CONF_VARS']['LOG']['Mediadreams']['MdUnreadnews']['Slot']['writerConfiguration'] = [
//            LogLevel::INFO => [
//                FileWriter::class => [
//                    'logFile' => \TYPO3\CMS\Core\Core\Environment::getVarPath() . '/log/typo3_UnreadNews.log'
//                ]
//            ]
//        ];

    }
);
