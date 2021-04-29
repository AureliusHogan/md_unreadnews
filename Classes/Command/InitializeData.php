<?php

namespace Mediadreams\MdUnreadnews\Command;

/**
 *
 * This file is part of the "Unread news" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2021 Erich Manser <erich.manser@avibus.eu>
 *
 */

use Georgringer\News\Domain\Repository\NewsRepository;
use Mediadreams\MdUnreadnews\Domain\Repository\UnreadnewsRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Class Cleanup
 * @package Mediadreams\MdUnreadnews\Command
 */
class InitializeData extends Command
{
    /**
     * @var io
     */
    protected $io;

    /**
     * @var connectionPool
     */
    protected $connectionPool;

    /**
     * @var UnreadnewsRepository
     */
    protected $unreadnewsRepository;

    /**
     * @var typoscriptSettings
     */
    protected $typoscriptSettings;

    /**
     * @var pid
     */
    protected $pid;

    /**
     * Cleanup constructor.
     * @param null $name
     */
    public function __construct($name = null)
    {
        parent::__construct($name);

        // get objectManager
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        // get unreadnewsRepository
        $this->unreadnewsRepository = $this->objectManager->get(UnreadnewsRepository::class);

        // create the connectionPool connection :-)
        $this->connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $this->typoscriptSettings = $this->getTyposcriptSettings();
        $this->pid = !empty($this->typoscriptSettings['storagePid'])? trim($this->typoscriptSettings['storagePid']):0;
//        $allowedCategories = GeneralUtility::trimExplode(',', $this->typoscriptSettings['categories'], true);

    }

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setDescription('Create unread information for a list of news uids')
            ->setHelp('This will remove existing and create unread information for all users for the specified news.')
            ->addArgument(
                'news',
                InputArgument::OPTIONAL,
                'Comma separated list of news uids (integer)'
            )
            ->addOption(
                'removeOnly',
                'r',
                InputOption::VALUE_OPTIONAL,
                'Removes existing entries without creating new data.'
            );
    }

    /**
     * Executes the command deleting old unread information
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title($this->getDescription());

        $newsUids = explode(',', $input->getArgument('news'));

        if($input->getOption('removeOnly')) {
            // remove existing data for the uids
            $this->removeUnreadInfo($newsUids);
            $this->io->success('All entries, for ' . implode(',', $newsUids) . ' are removed.');

        } else {

            $feuserData = $this->getUsers();
            $newsList = $this->getNews($newsUids);

            $recreatedUids = [];
            foreach ($newsList as $news) {
                // remove existing data for the uids
                $this->removeUnreadInfo([$news['uid']]);

                // todo: check the allowed categories
                $this->saveUnreadInfo($news, $feuserData);
                $recreatedUids[] = $news['uid'];
            }

            $this->io->success('All entries, for ' . implode(',', $recreatedUids) . ' are created.');
        }


        return 0;
    }

    /**
     * get the users to work with
     *
     * @return array
     */
    protected function getUsers() : array
    {
        // find users
        $queryBuilderFeusers = $this->connectionPool->getQueryBuilderForTable('fe_users');
        $feuserData = $queryBuilderFeusers
            ->select('fe_users.uid')
            ->from('fe_users');

        // if $allowedGroup is set, just find users with given group
        $allowedGroup = trim($this->typoscriptSettings['feGroup']);
        if ($allowedGroup) {
            $feuserData = $feuserData->where(
                $queryBuilderFeusers->expr()->inSet(
                    'usergroup',
                    $queryBuilderFeusers->createNamedParameter($allowedGroup, \PDO::PARAM_INT)
                )
            );
        }

        // finally get data
        return $feuserData
            ->execute()
            ->fetchAll();
    }

    /**
     * get the news to work with
     *
     * @param array $newsUids Comma separated list of news uids.
     * @return array
     */
    protected function getNews(array $newsUids) :array
    {
        $queryNews = $this->connectionPool->getQueryBuilderForTable('tx_news_domain_model_news');
        $queryNews
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $queryNews
            ->select('uid','datetime','hidden','starttime','endtime')
            ->from('tx_news_domain_model_news')
            ->where(
                $queryNews->expr()->in('uid', $queryNews->createNamedParameter($newsUids, Connection::PARAM_INT_ARRAY))
            );

        if($this->io->isVeryVerbose()) {
            $this->io->writeln('<info>List of News SQL...</info>' . $queryNews->getSQL() . ' Values ' . print_r($queryNews->getParameters()));
        }

        $newsList = $queryNews
            ->execute()
            ->fetchAll();

        if($this->io->isVeryVerbose()) {
            $this->io->writeln('<info>List of News ...</info>' . print_r($newsList));
        }
        return $newsList;
    }

    /**
     * saveUnreadInfo
     *
     * @param array $fieldArray array of data to add
     * @param array $feuserData List of all users
     *
     * @return void
     */
    protected function saveUnreadInfo(array $fieldArray, array $feuserData) : void
    {
        // if there is some data, prepare and save it
        if (count($feuserData) > 0) {
            // prepare data to save
            $timestamp = time();
            foreach ($feuserData as $data) {
                $dataArray[] = [
                    'pid'           => $this->pid,
                    'news'          => $fieldArray['uid'],
                    'feuser'        => $data['uid'],
                    'news_datetime' => $fieldArray['datetime'],
                    'tstamp'        => $timestamp,
                    'crdate'        => $timestamp,
                    'hidden'        => $fieldArray['hidden'],
                    'starttime'     => $fieldArray['starttime'],
                    'endtime'       => $fieldArray['endtime'],
                ];
            }

            $colNamesArray = ['pid', 'news', 'feuser', 'news_datetime', 'tstamp', 'crdate', 'hidden', 'starttime', 'endtime'];

            $dbConnectionUnreadnews = $this->connectionPool->getConnectionForTable('tx_mdunreadnews_domain_model_unreadnews');
            $dbConnectionUnreadnews->bulkInsert(
                'tx_mdunreadnews_domain_model_unreadnews',
                $dataArray,
                $colNamesArray
            );
        }

        if($this->io->isVerbose()) {
            $this->io->success('Added all entries, for news with id: ' . $fieldArray['uid'] . '.');
        }

    }

    /**
     * remove unread data for the mentioned id's
     *
     * @param array $uids Comma separated list of news uids to be removed from repository
     * @return void
     */
    protected function removeUnreadInfo(array $uids): void
    {

        $queryNews = $this->connectionPool->getQueryBuilderForTable('tx_mdunreadnews_domain_model_unreadnews');
        $queryNews
            ->delete('tx_mdunreadnews_domain_model_unreadnews')
            ->where(
                $queryNews->expr()->in('news', $queryNews->createNamedParameter($uids, Connection::PARAM_INT_ARRAY))
            );

        if($this->io->isVeryVerbose()) {
            $this->io->writeln('<info>List of News SQL to remove ...</info>' . $queryNews->getSQL() . ' Values ' . print_r($queryNews->getParameters()));
        }

        $queryNews->execute();

        if($this->io->isVerbose()) {
            $this->io->success('Removed all entries, for news with ids: ' . implode(',', $uids) . '.');
        }

    }

    /**
     * Get typoscript settings
     *
     * @return array
     */
    protected function getTyposcriptSettings()
    {
        // get typoscript settings
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $extbaseFrameworkConfiguration = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
        );

        // typoscript settings for ext:md_unreadnews plugin "unread"
        return $extbaseFrameworkConfiguration['plugin.']['tx_mdunreadnews_unread.']['settings.'];
    }

}
