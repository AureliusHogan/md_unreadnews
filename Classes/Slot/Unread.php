<?php
namespace Mediadreams\MdUnreadnews\Slot;

use Mediadreams\MdUnreadnews\Hooks\TCEmainHook;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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

/**
 * NewsController
 */
class Unread extends TCEmainHook {

    /**
     * function to add unread status
     *
     * @param object $news The news object
     * @param object $obj The controller object
     * @return void
     */
    public function addUnread(object $news, object $obj) : void
    {

        // get uid of new record
        $newsUid = $news->getUid();

        $typoscriptSettings = $this->getTyposcriptSettings();
        $allowedCategories = GeneralUtility::trimExplode(',', $typoscriptSettings['categories'], true);

        $fieldArray = [
            'datetime' => $news->getDatetime()->format('U'),
            'hidden' => $news->getHidden(),
            'starttime' => $news->getStarttime() ? $news->getStarttime()->format('U') : 0,
            'endtime' => $news->getEndTime() ? $news->getEndtime()->format('U') : 0
        ];

        // if there are categories configured in typoscript
        if (count($allowedCategories) > 0) {
            // get selected categories in news record
            $categories = [$news->getFirstCategory()->getUid()];

            // check, if category in news record is a category which is configured in typoscript
            $matchedCategories = array_intersect($allowedCategories, $categories);

            // if $matchedCategories has at least one matched element, add unread info
            if (count($matchedCategories) > 0) {
                // add unread data
                $this->saveUnreadInfo($newsUid, $fieldArray, $typoscriptSettings);
            }
        } else { // if no categories configured in typoscript, add always unread info
            $this->saveUnreadInfo($newsUid, $fieldArray, $typoscriptSettings);
        }
    }

    /**
     * function to add unread status
     *
     * @param object $news The news object
     * @param object $obj The controller object
     * @return void
     */
    public function updateUnread(object $news, object $obj) : void
    {
        $newsUid = $news->getUid();
        $fieldArray = [
            'datetime' => $news->getDatetime()->format('U'),
            'hidden' => $news->getHidden(),
            'starttime' => $news->getStarttime() ? $news->getStarttime()->format('U') : 0,
            'endtime' => $news->getEndTime() ? $news->getEndtime()->format('U') : 0
        ];

        $typoscriptSettings = $this->getTyposcriptSettings();
//        $this->logger->info(__FUNCTION__ , $typoscriptSettings);

        if($typoscriptSettings['setUnreadIfUpdated']){
            $this->removeUnreadInfo($newsUid);
            $this->saveUnreadInfo($newsUid, $fieldArray, $typoscriptSettings);
        } else {
            $this->updateUnreadInfo($newsUid, $fieldArray);
        }
    }

    /**
     * function to remove unread status
     *
     * @param object $news The news object
     * @param object $obj The controller object
     * @return void
     */
    public function removeUnread(object $news, object $obj) : void
    {
        $newsUid = $news->getUid();

        $this->removeUnreadInfo( $newsUid );
    }

}
