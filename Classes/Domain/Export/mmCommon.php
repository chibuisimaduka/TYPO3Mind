<?php
/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2011 Cyrill Schumacher <Cyrill@Schumacher.fm>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */

/**
 * @package typo3mind
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 *
 */
abstract class Tx_Typo3mind_Domain_Export_mmCommon /*

  this has been put into the object $mmFormat which is a factory object
  extends Tx_Typo3mind_Domain_Export_Formats_Freemind

 */
{
    /* is there a way to dynamically extend from different classes? i think not ... :-( */

    /**
     * pageUid of the current page
     *
     * @var int
     */
    protected $pageUid;

    /**
     * the http host with http prefix
     *
     * @var array
     */
    protected $httpHosts;

    /**
     * TS settings
     *
     * @var array
     */
    public $settings;

    /**
     * t3MindRepository
     *
     * @var Tx_Typo3mind_Domain_Repository_T3mindRepository
     */
    protected $t3MindRepository;

    /**
     * Lists all valid SysDomains for viewing pages... will overwrite httpHost ...
     *
     * @var array
     */
    protected $sysDomains;

    /**
     * Lists all Sys Languages
     *
     * @var array
     */
    protected $sysLanguages;

    /**
     * Check what type your are running ...
     *
     * @var array
     */
    protected $mapMode;

    /**
     * assoc array containing the ID and the name of the user
     * @var array
     */
    public $cruser_id;

    /**
     * contains all entries from a table in the database
     * @var array
     * */
    protected $dbTables;

    /**
     *
     * @var Tx_Typo3mind_Domain_Export_Formats_[The Format]
     */
    protected $mmFormat;

    /**
     *
     * @var array
     */
    private $_iconCache;

    /**
     * Constructor
     *
     * @param array $settings
     * @param Tx_Typo3mind_Domain_Repository_T3mindRepository $t3MindRepository
     * @return void
     */
    public function __construct(array $settings, Tx_Typo3mind_Domain_Repository_T3mindRepository $t3MindRepository)
    {
        $this->settings = $settings;
        $this->t3MindRepository = $t3MindRepository;
        $this->setmapMode();
        $this->initSysDomains();
        $this->initSysLanguages();
        $this->setHttpHosts();
        $this->setCruserId();

        $tsFormat = !empty($this->settings['exportFormatClass']) ? $this->settings['exportFormatClass'] : 'Freemind';
        $makeInstance = 'Tx_Typo3mind_Domain_Export_Formats_' . $tsFormat;

        if (!class_exists($makeInstance)) {
            throw new Exception('Couldn\'t find class ' . $makeInstance . '. Maybe you have a miss configuration in your TypoScript Setting exportFormatClass / Error Number: 1336631312');
        }
        $this->mmFormat = t3lib_div::makeInstance($makeInstance, $this);
    }

    /**
     * gets the mind map export object
     *
     * @return Tx_Typo3mind_Domain_Export_Formats_[The Format]
     */
    public function getmmFormat()
    {
        return $this->mmFormat;
    }

    /**
     * sets the CruserId
     *
     * @param    none
     * @return    void
     */
    private function setCruserId()
    {
        $this->cruser_id = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid,concat(realName,\'(\',username,\')\') as user', 'be_users', '', '', '', '', 'uid');
    }

    /**
     * gets the User by Id
     *
     * @param    integer $uid
     * @return    string the user
     */
    public function getUserById($uid)
    {
        return isset($this->cruser_id[$uid]) ? $this->cruser_id[$uid]['user'] : $this->translate('UserNotFound') . ' (' . $uid . ')';
    }

    /**
     * sets a array what kind of map will be generated
     *
     * @param    none
     * @return    void
     */
    private function setmapMode()
    {

        $this->mapMode = array(
            'befe' => $this->settings['mapMode'], /* bad content from outside ... so use properly */
            'isbe' => stristr($this->settings['mapMode'], 'backend') !== false,
        );
    }

    /**
     * checks if map mode is set to backend mode
     *
     * @return boolean
     */
    public function isMapModeBE()
    {
        return $this->mapMode['isbe'];
    }

    /**
     * checks if map mode is set to FE mode, gets the value from typoscript
     *
     * @return string
     */
    public function getMapModeBEFE()
    {
        return $this->mapMode['befe'];
    }

    /**
     * sets the private property http_host for frontend and backend
     *
     * @param    none
     * @return    void
     */
    private function setHttpHosts()
    {
        /* maybe one day define different links in setup.txt */
        $this->httpHosts = array(
            'frontend' => t3lib_div::getIndpEnv('TYPO3_REQUEST_HOST') . '/',
            'backend' => t3lib_div::getIndpEnv('TYPO3_REQUEST_HOST') . '/',
        );
    }

    /**
     * gets the subfolder where TYPO3 is installed
     * @return string
     */
    protected function getRelInstallPathPart()
    {
        // getting the subfolders where typo3 is installed
        // Bug #37931
        $path = '';
        if (PATH_site . TYPO3_mainDir <> PATH_typo3) {
            $path = str_replace(array(PATH_site, TYPO3_mainDir), array('', ''), PATH_typo3);
            $path .= '/';
        }
        return $path;
    }


    /**
     * returns the http_host
     *
     * @param    none
     * @return    string BE http host
     */
    public function getBEHttpHost()
    {

        return $this->httpHosts['backend'] . $this->getRelInstallPathPart();
    }

    /**
     *
     * @param int $page_Uid
     * @return string http host frontend
     */
    public function getFEHttpHost($page_Uid)
    {
        if (isset($this->sysDomains[$page_Uid])) {
            $httpWhat = t3lib_div::getIndpEnv('TYPO3_SSL') ? 'https' : 'http';
            /* how can you be sure to know that this FE hosts supports SSL when you
             * generate the .mm file using a different BE host with SSL? */
            $this->httpHosts['frontend'] = $httpWhat . '://' . $this->sysDomains[$page_Uid] . '/';
        }
        return $this->httpHosts['frontend'] . $this->getRelInstallPathPart();
    }

    /**
     * Translate key from locallang.xml.
     *
     * @param string $key Key to translate
     * @param array $arguments Array of arguments to be used with vsprintf on the translated item.
     * @return string Translation output.
     */
    public function translate($key, $arguments = null)
    {
        return Tx_Extbase_Utility_Localization::translate($key, 'Typo3mind', $arguments);
    }

    /**
     * gets microtime string.
     *
     * @return float microtime.
     */
    protected function getMicrotime()
    {
        $m = explode(' ', microtime());
        return mt_rand() . '' . ((string)$m[0]);
    }

    /**
     * strip tags with preg_replace whitespaces
     *
     * @param string $string
     * @return string
     */
    public function strip_tags($string)
    {
        return preg_replace('~\s+~', ' ', strip_tags($string));
    }

    /**
     * returns an array as an html table
     * @param array $array
     * @param int $width
     * @return string the HTML table
     */
    public function array2Html2ColTable($array, $width = 300)
    {
        $nodeHTML = array('<table width="' . $width . '" border="0" cellpadding="3" cellspacing="0">');
        $i = 0;
        foreach ($array as $k => $v) {

            $nodeHTML[] = '<tr style="background-color:' . (
            $i % 2 == 0 ? '#ffffff' : '#ececec'
            ) . ';" valign="top"><td>' . $k . '</td><td>' . htmlspecialchars($v) . '</td></tr>';
            $i++;
        }
        $nodeHTML[] = '</table>';

        return implode('', $nodeHTML);
    }

    /**
     * gets all SysDomains
     *
     * @return void
     */
    private function initSysDomains()
    {
        $this->sysDomains = array();
        $result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('pid,domainName', 'sys_domain', 'hidden=0', '', 'pid, sorting DESC');
        while ($r = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result)) {
            $this->sysDomains[$r['pid']] = $r['domainName'];
        }
    }

    /**
     * gets all Sys Languages into private property
     *
     * @return void
     */
    private function initSysLanguages()
    {
        /*
          mod.SHARED {
          defaultLanguageFlag = de.gif
          defaultLanguageLabel = german
          }
         */
        $defaultLanguageFlag = (isset($this->settings['defaultLanguageFlag']) && strlen($this->settings['defaultLanguageFlag']) == 2) ? $this->settings['defaultLanguageFlag'] : 'de';

        /* pageUid see T3mindController.php */
        $modSharedTSconfig = t3lib_BEfunc::getModTSconfig($this->settings['pageUid'], 'mod.SHARED');

        if (!isset($modSharedTSconfig['properties']['defaultLanguageFlag'])) {
            $modSharedTSconfig['properties']['defaultLanguageFlag'] = $defaultLanguageFlag;
        }
        if (!isset($modSharedTSconfig['properties']['defaultLanguageLabel'])) {
            $modSharedTSconfig['properties']['defaultLanguageLabel'] = $defaultLanguageFlag;
        }

        // fallback non sprite-configuration
        if (preg_match('/\.gif$/', $modSharedTSconfig['properties']['defaultLanguageFlag'])) {
            $modSharedTSconfig['properties']['defaultLanguageFlag'] = str_replace('.gif', '', $modSharedTSconfig['properties']['defaultLanguageFlag']);
        }


        $this->sysLanguages = array(
            0 => array(
                'title' => strlen($modSharedTSconfig['properties']['defaultLanguageLabel']) ? $modSharedTSconfig['properties']['defaultLanguageLabel'] . ' (' . $GLOBALS['LANG']->sl('LLL:EXT:lang/locallang_mod_web_list.xml:defaultLanguage') . ')' : $GLOBALS['LANG']->sl('LLL:EXT:lang/locallang_mod_web_list.xml:defaultLanguage'),
                'flag' => $modSharedTSconfig['properties']['defaultLanguageFlag'],
            )
        );
        $result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid,title,flag', 'sys_language', '', '', '');
        while ($r = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result)) {
            $this->sysLanguages[$r['uid']] = $r;
        }
    }

    /**
     * gets details for a Sys Languages
     *
     * @param int $language_id
     * @param string $column (title or flag)
     * @return string
     */
    public function getSysLanguageDetails($language_id, $column)
    {


        if ($column == 'flag') {
            $return = 'typo3/gfx/flags/' . $this->sysLanguages[$language_id][$column] . '.gif';
        } else {
            $return = $this->sysLanguages[$language_id][$column];
        }

        return $return;
    }

    /**
     * format the size by bytes ... outputs human readable...
     *
     * @param int $bytes
     * @return int bytes
     */
    protected function formatBytes($bytes)
    {
        $bytes = (int)$bytes;

        if ($bytes == 0) {
            $return = 'n/a';
        } else {

            $sizes = array('  B', ' KB', ' MB', ' GB', ' TB', ' PB', ' EB', ' ZB', ' YB');
            $return = '';
            $return = (round($bytes / pow(1024, ($i = floor(log($bytes, 1024)))), 2) . $sizes[$i]);
        }
        return str_pad($return, 15, ' ', STR_PAD_LEFT);
    }

    /**
     * gets all recurSive Directory size / func could be improved with scandir()
     * @param string $dir_name
     * @return integer bytes
     */
    protected function getDirSize($dir_name)
    {
        $dir_size = 0;
        if (is_dir($dir_name)) {
            if ($dh = @opendir($dir_name)) {
                while (($file = readdir($dh)) !== false) {
                    if ($file != '.' && $file != '..') {
                        if (is_file($dir_name . '/' . $file)) {
                            $dir_size += filesize($dir_name . '/' . $file);
                        }
                        // check for any new directory inside this directory
                        if (is_dir($dir_name . '/' . $file)) {
                            $dir_size += $this->getDirSize($dir_name . '/' . $file);
                        }
                    }
                }
            }
            @closedir($dh);
        }
        return $dir_size;
    }

    /**
     * gets the URL to the detailed ext description on the TER
     *
     * @param string $extName
     * @return string
     */
    protected function getTerURL($extName)
    {
        return str_replace('###extname###', $extName, $this->settings['TerURL2Ext']);
    }

    /**
     * tries to get the plaintext password from an md5 string... returns false on failure
     *
     * @param string $md5
     * @return string|false
     */
    protected function getPlainTextPasswordFromMD5($md5)
    {
        if ($this->settings['MD5HashCrackSource'] == 'internal') {
            return Tx_Typo3mind_Utility_UnsecurePasswords::getPlainPW($md5);
        } else {
            return false;
        }
    }

    /**
     * if defined in the settings TS it returned the color count for alternating colors
     *
     * @param string $methodName
     * @param integer $rowCounter incremental
     * @param string $type what kind of color ...
     * @return string
     */
    protected function getDesignAlternatingColor($methodName, $rowCounter, $type = 'BACKGROUND_COLOR')
    {
        if (!isset($this->settings['design'][$methodName])) {
            $this->settings['design'][$methodName] = array($type => array());
        } elseif (!isset($this->settings['design'][$methodName][$type])) {
            $this->settings['design'][$methodName][$type] = array();
        }

        $count = count($this->settings['design'][$methodName][$type]);
        $count = $count == 0 ? 1 : $count;

        $mod = $rowCounter % $count;
        return isset($this->settings['design'][$methodName][$type][$mod]) ? $this->settings['design'][$methodName][$type][$mod] : '';
    }

    /* </getDesignAlternatingColor> */

    /**
     * if defined in the settings TS it returned the edge width
     *
     * @param string $methodName
     * @return string
     */
    protected function getDesignEdgeWidth($methodName)
    {
        if (!isset($this->settings['design'][$methodName])) {
            $this->settings['design'][$methodName] = array('EDGE_WIDTH' => 0); /* no edge width */
        } elseif (!isset($this->settings['design'][$methodName]['EDGE_WIDTH'])) {
            $this->settings['design'][$methodName]['EDGE_WIDTH'] = 0;
        }

        return (int)$this->settings['design'][$methodName]['EDGE_WIDTH'];
    }

    /* </getDesignEdgeWidth> */

    /**
     * gets the RSS title
     *
     * @param SimpleXMLElement $rssContent
     * @return string
     */
    private function _getRssTitle(SimpleXMLElement $rssContent)
    {
        return isset($rssContent->channel->title) ? $rssContent->channel->title : $rssContent->title;
    }

    /**
     * gets the RSS link
     *
     * @param SimpleXMLElement $rssContent
     * @return string
     */
    private function _getRssLink(SimpleXMLElement $rssContent)
    {
        if (isset($rssContent->channel->link)) {
            return $rssContent->channel->link;
        } else {
            $attr = (array)$rssContent->link->attributes();
            return $attr['@attributes']['href'];
        }
    }

    /**
     * if defined in the settings TS it returned the edge width
     *
     * @param SimpleXMLElement $xmlNode
     * @return boolean
     */
    protected function RssFeeds2Node(SimpleXMLElement $xmlNode)
    {

        if (count($this->settings['TYPO3SecurityRssFeeds']) == 0) {
            return false;
        }

        foreach ($this->settings['TYPO3SecurityRssFeeds'] as $index => $feedURL) {

            $rssContent = simplexml_load_string($this->getURLcache($feedURL));
            if ($rssContent) {

                $rssHeadNode = $this->mmFormat->addNode($xmlNode, array(
                    'FOLDED' => 'true',
                    'TEXT' => htmlspecialchars($this->_getRssTitle($rssContent)),
                    'LINK' => $this->_getRssLink($rssContent)
                ));

                /* RSS 2.0 */
                if (isset($rssContent->channel) && is_object($rssContent->channel)) {
                    foreach ($rssContent->channel->item as $index => $item) {

                        $htmlContent = array();
                        $htmlContent[] = '<p>' . htmlspecialchars($item->author) . '</p>';
                        $htmlContent[] = '<p>' . htmlspecialchars($item->pubDate) . '</p>';
                        $htmlContent[] = '<p>' . $item->description . '</p>';

                        $this->mmFormat->addRichContentNote($rssHeadNode, array(
                            'TEXT' => htmlspecialchars($item->title), 'LINK' => $item->link
                        ), implode('', $htmlContent));
                    }
                    /* endforeach */
                } /* endif rss 2.0 */ else {
                    $this->mmFormat->addNode($xmlNode, array(
                        'TEXT' => 'RSS Feed not readable',
                    ));
                }
                /* endif rss atom */
            }
            /* endif $rssContent */
        }
        /* endforeach */
        return true;
    }

    /* </RssFeeds2Node> */

    /**
     * gets the enc key with a specific length
     *
     * @param int $length
     * @return string
     */
    protected function getencryptionKey($length)
    {
        $encK = str_repeat('X3Pk{2b#+eG5d}vi\?47RJ' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'], $length);
        return substr($encK, 0, $length);
    }

    /**
     * fetches a URL and saves it in a cache for a lifetime of 3 days
     *
     * @param string $url
     * @return string
     */
    protected function getURLcache($url)
    {

        $urlContent = false;
        $cacheFile = PATH_site . 'typo3temp/t3m_' . md5($_SERVER['HTTP_HOST'] . $url);
        if (!file_exists($cacheFile) || filemtime($cacheFile) < (time() - (3600 * 24 * 3))) {
            $urlContent = trim(t3lib_div::getURL($url));
            if (!empty($urlContent)) {
                $encK = $this->getencryptionKey(strlen($urlContent));
                /* echo "Wrote Cache $cacheFile<br>\n"; */
                t3lib_div::writeFile($cacheFile, ($urlContent ^ $encK)); /* hihihi ;-) */
            } else {
                return false;
            }
        } else {
            /* echo "Read Cache $cacheFile<br>\n"; */
            $urlContent = implode('', file($cacheFile));
            $encK = $this->getencryptionKey(strlen($urlContent));
            $urlContent = ($urlContent ^ $encK);
        }
        return $urlContent;
    }

    /**
     * returns formated date, used the global T3 config
     *
     * @param int $unixTimeStamp
     * @return string formated date
     */
    public function getDateTime($unixTimeStamp)
    {
        $unixTimeStamp = (int)$unixTimeStamp;
        $dateFormat = $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'];
        $timeFormat = $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'];
        return date($dateFormat . ', ' . $timeFormat, $unixTimeStamp);
    }

    /**
     * generates a edit URL
     * @param int $uid
     * @return string the URL
     */
    private function _getNodePropertyEditUrl($uid)
    {

//		if( !isset($GLOBALS['TBE_MODULES']['_configuration']['web_Typo3mindFm2be']) ){
//			$moduleConfig = $GLOBALS['TBE_MODULES']['_configuration']['web_Typo3mindFm2be'];
//
//		} else {
//			/* we have workspaces */
//			echo '<pre>';

        foreach ($GLOBALS['TBE_MODULES']['_configuration'] as $modName => $config) {
            if (stristr($modName, 'typo3mind') !== false) {
                $moduleConfig = $config;
                break;
            }
        }
//		}

        $name = $moduleConfig['name'];
        $nameLower = strtolower($moduleConfig['name']);
        $extName = strtolower($moduleConfig['extensionName']);

        $urlEdit = $this->getBEHttpHost() .
            'typo3/mod.php?M=' .
            $name
            . '&tx_' . $extName . '_' . $nameLower . '[action]=dispatch&tx_' . $extName . '_' . $nameLower . '[controller]=T3mind&id=' . $uid;

        $urlEdit = str_replace('&', '&amp;', $urlEdit);
        return $urlEdit;
    }

    /**
     * gets the note content from a DB row
     *
     * @param string $tableName if defined then ALL rows specified in the TCA will be printed...
     * @param array $row from the database table
     * @return    string
     */
    public function getNoteContentFromRow($tableName, $row)
    {
        global $TCA;

        $htmlContent = array('<table>');

        if ($this->mapMode['isbe']) {

            $urlEdit = $this->_getNodePropertyEditUrl($row['uid']);

            $htmlContent[] = '<tr valign="top"><td colspan="2"><a href="' .
                $urlEdit
                . '">' . $this->translate('tree.typo3.EditNodeProperties') . '</a></td></tr>';
        }

        $htmlContent[] = $this->_getNoteTableRow('UID', $row['uid']);
        unset($row['uid']);

        $col = 'crdate';
        $label = $this->_getNoteTableRowLabel($tableName, $col, 'Created');
        $htmlContent[] = $this->_getNoteTableRow($label, $this->getDateTime($row[$col]));
        unset($row[$col]);

        $col = 'cruser_id';
        $label = $this->_getNoteTableRowLabel($tableName, $col, 'Created by');
        $htmlContent[] = $this->_getNoteTableRow($label, $this->getUserById($row[$col]));
        unset($row[$col]);

        $col = 'tstamp';
        $label = $this->_getNoteTableRowLabel($tableName, $col, 'Last update');
        $htmlContent[] = $this->_getNoteTableRow($label, $this->getDateTime($row[$col]));
        unset($row[$col]);

        if (isset($row['sys_language_uid'])) {
            $col = 'sys_language_uid';
            $label = $this->_getNoteTableRowLabel($tableName, $col, 'Language ID');

            if ($row[$col] >= 0) {
                $sluCon = '(' . $row[$col] . ') ' . $this->getSysLanguageDetails($row[$col], 'title') .
                    ' <img src="' . $this->getBEHttpHost() . $this->getSysLanguageDetails($row[$col], 'flag') . '"/>';
            } else {
                $sluCon = '(' . $row[$col] . ') ' . $this->getSysLanguageDetails($row[$col], 'title') . ' All';
            }
            $htmlContent[] = $this->_getNoteTableRow($label, $sluCon);

            unset($row[$col]);
        }


        if (isset($row['starttime']) && $row['starttime'] > 0) {
            $col = 'starttime';
            $label = $this->_getNoteTableRowLabel($tableName, $col, 'Starttime');
            $htmlContent[] = $this->_getNoteTableRow($label, $this->getDateTime($row[$col]));
        }
        unset($row['starttime']);
        if (isset($row['endtime']) && $row['endtime'] > 0) {
            $col = 'endtime';
            $label = $this->_getNoteTableRowLabel($tableName, $col, 'Endtime');
            $htmlContent[] = $this->_getNoteTableRow($label, $this->getDateTime($row[$col]));
        }
        unset($row['endtime']);
        unset($row['uid']);
        unset($row['pid']);
        unset($row['titInt0']);
        unset($row['title']);
        unset($row['doktype']);
        unset($row['shortcut_mode']);
        unset($row['module']);
        unset($row['deleted']);
        unset($row['hidden']);
        unset($row['disable']);

        /* list user defined columsn for a table listet in a sysfolder */
        if ($tableName <> '' && count($row) > 0) {

            $bodyLength = isset($this->settings['SysFolderContentListTextMaxLength']) ? (int)$this->settings['SysFolderContentListTextMaxLength'] : 250;

            foreach ($row as $colName => $colVal) {
                $noLtGtReplace = 0;

                if ($colVal <> '') {
                    $label = $this->_getNoteTableRowLabel($tableName, $colName, $colName);
                    $tcaEval = isset($TCA[$tableName]['columns'][$colName]['config']['eval']) ? $TCA[$tableName]['columns'][$colName]['config']['eval'] : '';
                    $tcaType = isset($TCA[$tableName]['columns'][$colName]) ? strtolower($TCA[$tableName]['columns'][$colName]['config']['type']) : 'text';

                    /* <TemplaVoila> */
                    if ($tableName == 'tx_templavoila_tmplobj' && in_array($colName, array('fileref_md5', 'fileref', 'datastructure'))) {

                        if ($colName == 'fileref_md5' && isset($row['fileref_md5']) &&
                            isset($row['fileref']) && file_exists(PATH_site . $row['fileref'])
                        ) {

                            $currentMD5 = @md5_file(PATH_site . $row['fileref']);
                            if ($currentMD5 <> $row['fileref_md5']) {
                                $label .= $this->mmFormat->convertLTGT(' <img src="' . $this->getBEHttpHost() . 'typo3/sysext/t3skin/icons/gfx/icon_warning.gif"/>');
                                $colVal = ' New: ' . $currentMD5 . '<br />Old: ' . $colVal;
                            }
                        } elseif ($colName == 'datastructure' || $colName == 'fileref') {
                            $colVal = $this->_value2ATag($colVal);
                        }
                    } /* </TemplaVoila> */

                    /* <Templates> */ elseif (($tableName == 'sys_template' || $tableName == 'pages') &&
                        in_array($colName, array('include_static_file', 'constants', 'config', 'TSconfig'))
                    ) {
                        /* @TODO better implementing of tables which has TS column */
                        if ($colName == 'include_static_file') {
                            $colVal = implode('<br />', explode(',', $colVal));
                        }
                        if ($colName == 'constants' || $colName == 'config' || $colName == 'TSconfig') {
                            $noLtGtReplace = 1;

                            /* @todo link external TS files via eID ... still to think about it ...
                            $colVal = Tx_Typo3mind_Utility_Helpers::TSReplaceFileLinkWithHref($colVal);
                             */
                            $colVal = $this->mmFormat->convertLTGT('<pre>') . trim($colVal) . $this->mmFormat->convertLTGT('</pre>');
                        }
                    } /* </Templates> */

                    /* <default values> */ elseif (stristr($tcaEval, 'date') !== false) {
                        $colVal = $this->getDateTime($colVal);
                    } elseif ($tcaType == 'text' || $tcaType == 'input') {
                        /* we can't relay on the user, that all HTML is XML valid ... */
                        $colVal = strip_tags($colVal);
                        if (strlen($colVal) > $bodyLength) {
                            $colVal = preg_replace('/^(.{' . $bodyLength . '}\S*).*$/s', '\\1 ...', $colVal);
                        }
                        $colVal = preg_replace('~[\r\n]+~', "\n", $colVal);
                        $colVal = nl2br($colVal);
                    }
                    /* </default values> */

                    $htmlContent[] = $this->_getNoteTableRow($label, $colVal, $noLtGtReplace);
                }
                /* endif */
            }
            /* endforeach */
        }
        /* endif $tableName */

        $htmlContent[] = '</table>';
        return implode('', $htmlContent);
    }

    /* </getNoteContentFromRow> */

    /**
     * links a string
     *
     * @param string $string
     * @return string
     */
    private function _value2ATag($string)
    {
        return '<a href="' . $this->getBEHttpHost() . $string . '">' . $string . '</a> ' . $this->translate('value2ATag');
    }

    /**
     * generates a HTML table row
     *
     * @param string $label
     * @param string $value
     * @param boolean $noLtGtReplace
     * @return    void
     * @see getNoteContentFromRow
     */
    private function _getNoteTableRow($label, $value, $noLtGtReplace = 0)
    {
        $value = htmlspecialchars($value);
        if ($noLtGtReplace == 0) {
            $value = str_replace(array('&lt;', '&gt;'), array('|lt|', '|gt|'), $value);
        }
        return '<tr valign="top"><td>' . htmlspecialchars($label) . '</td><td>' . $value . '</td></tr>';
    }

    /**
     * generates a label for a HTML table row from the TCA array
     * @see getNoteContentFromRow
     * @global array $TCA
     * @param string $tableName
     * @param string $col
     * @param string $alt
     * @return string
     */
    private function _getNoteTableRowLabel($tableName, $col, $alt)
    {
        global $TCA;
        $label = isset($TCA[$tableName]['columns'][$col]['label']) ? $GLOBALS['LANG']->sL($TCA[$tableName]['columns'][$col]['label']) : $alt;
        if (empty($label)) {
            $label = $alt;
        }
        return $label;
    }

    /**
     * gets the whole tt content entry for a page
     * @param SimpleXMLElement $xmlNode
     * @param array $pageRecord
     * @param boolean $isLastPageNode
     */
    public function getTTContentFromPage(SimpleXMLElement $xmlNode, $pageRecord, $isLastPageNode = 0)
    {


        $this->loadDatabaseTable('tx_templavoila_tmplobj', array('title'));

        $ttContentNode = $xmlNode;
        if ($isLastPageNode == 0) {
            $ttContentNode = NULL;
            $ttContentNode = $this->mmFormat->addNode($xmlNode, array(
                'FOLDED' => 'true',
                'TEXT' => 'Content Elements', // $this->translate('Content Elements'),
            ));
        }

        $orderBy = 'ORDER BY cCType desc,CType asc';

        $queryParts = array(
            'SELECT' => 'CType,count(*) cCType',
            'FROM' => 'tt_content',
            'WHERE' => 'pid=' . intval($pageRecord['uid']),
            'GROUPBY' => 'CType',
            'ORDERBY' => $GLOBALS['TYPO3_DB']->stripOrderBy($orderBy),
            'LIMIT' => ''
        );

        $result = $GLOBALS['TYPO3_DB']->exec_SELECT_queryArray($queryParts) or
            die('Please fix this error!<br>' . __FILE__ . ' Line ' . __LINE__ . ":\n<br>\n" . mysql_error() . "<hr>" . var_export($queryParts, 1));

        while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result)) {

            $cTypeInfo = $this->_ttcGetCTypeInfo($row);

            /* group the elements .. */
            $isGrouped = 0;
            if ($row['cCType'] > 1) {
                $attr = array(
                    'TEXT' => '(C:' . $row['cCType'] . ') ' . $cTypeInfo['txt'],
                );
                $ttContentNodeGrouped = $this->mmFormat->addImgNode($ttContentNode, $attr, $cTypeInfo['icon'], '');
                $isGrouped = 1;
                $this->_getTTContentGroup($ttContentNodeGrouped, $pageRecord, $row['CType'], $isGrouped);
            } else {
                /* this only works with an if/else contruct */
                $this->_getTTContentGroup($ttContentNode, $pageRecord, $row['CType'], $isGrouped);
            }
        }
    }

    /* </getTTContentFromPage> */

    /**
     * gets the whole tt content group entry for a page
     * @param SimpleXMLElement $xmlNode
     * @param array $pageRecord
     * @param string $CType
     * @param boolean $isGrouped
     */
    private function _getTTContentGroup(SimpleXMLElement $xmlNode, $pageRecord, $CType, $isGrouped = 0)
    {

        $orderBy = 'ORDER BY sorting';

        $pageRecord['uid'] = (int)$pageRecord['uid'];
        $queryParts = array(
            'SELECT' => '*',
            'FROM' => 'tt_content',
            'WHERE' => 'pid=' . $pageRecord['uid'] . ' and CType=\'' . addslashes($CType) . '\'',
            'GROUPBY' => '',
            'ORDERBY' => $GLOBALS['TYPO3_DB']->stripOrderBy($orderBy),
            'LIMIT' => ''
        );

        $result = $GLOBALS['TYPO3_DB']->exec_SELECT_queryArray($queryParts) or
            die('Please fix this error!<br>' . __FILE__ . ' Line ' . __LINE__ . ":\n<br>\n" . mysql_error() . "<hr>" . var_export($queryParts, 1));
        $dbCount = $GLOBALS['TYPO3_DB']->sql_num_rows($result);

        if ($dbCount) {

            /*
              $value = htmlspecialchars($value);
              if( $noLtGtReplace == 0 ){ $value = str_replace(array('&lt;','&gt;'),array('|lt|','|gt|'),$value); }
              return '<tr valign="top"><td>'.htmlspecialchars($label).'</td><td>'.$value.'</td></tr>';


              if DAM is installed get different image infos from DAM table

             */
            $htmlContent = $htmlRow = array();
            while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result)) {
                $htmlContent = $htmlRow = array(); // that's important

                $cTypeInfo = $this->_ttcGetCTypeInfo($row);

                $attr = array(
                    'TEXT' => array('(' . $row['uid'] . ')'),
                    'LINK' => $this->mapMode['isbe'] ? $this->getBEHttpHost() . 'typo3/alt_doc.php?edit[tt_content][' . $row['uid'] . ']=edit' : '',
                );
                if ($isGrouped == 0) {
                    $attr['TEXT'][] = $cTypeInfo['txt'];
                }

                if (isset($cTypeInfo['pluginTxt'])) {
                    $attr['TEXT'][] = '(' . $cTypeInfo['pluginTxt'] . ')';
                }

                if ($CType == 'templavoila_pi1' && isset($this->dbTables['tx_templavoila_tmplobj']) &&
                    isset($row['tx_templavoila_to']) && !empty($row['tx_templavoila_to'])
                ) {
                    $attr['TEXT'][] = $this->dbTables['tx_templavoila_tmplobj'][$row['tx_templavoila_to']]['title'] . ':';
                }


                if (!empty($row['header'])) {
                    $attr['TEXT'][] = htmlspecialchars(strip_tags($row['header']));
                } elseif (!empty($row['bodytext'])) {
                    $attr['TEXT'][] = htmlspecialchars(substr(strip_tags($row['bodytext']), 0, 50)) . '...';
                }

                /* General info about an item */
                $htmlContent[] = '<table>';
                $htmlRow['Created by'] = $this->getUserById($row['cruser_id']);
                $htmlRow['Created'] = $this->getDateTime($row['crdate']);
                $htmlRow['Last update'] = $this->getDateTime($row['tstamp']);

                // Now build the array into a table row
                foreach ($htmlRow as $rowCol1 => $rowCol2) {
                    $htmlContent[] = '<tr valign="top"><td><b>' . $rowCol1 . '</b></td><td>' . $rowCol2 . '</td></tr>';
                }
                $htmlContent[] = '</table>';


                /* print full HTML into the NOTE! nice formatting */
                if (!empty($row['bodytext'])) {
                    $htmlContent[] = '<hr/>';
                    $bodytext = Tx_Typo3mind_Utility_Helpers::convertT3Tags($row['bodytext']);
                    if ($bodytext) {
                        $htmlContent[] = htmlspecialchars(str_replace(array('&lt;', '&gt;', '<', '>'), array('|lt|', '|gt|', '|lt|', '|gt|'), $bodytext));
                    } else {
                        $htmlContent[] = htmlspecialchars($row['bodytext']);
                    }
                }

                $htmlContent = implode('', $htmlContent);

                $attr['TEXT'] = implode(' ', $attr['TEXT']);

                if ($isGrouped == 0) {
                    $aTtContentNode = $this->mmFormat->addImgNote($xmlNode, $attr, $cTypeInfo['icon'], '', $htmlContent);
                } else {
                    /* avoid duplicated icons */
                    $aTtContentNode = $this->mmFormat->addNote($xmlNode, $attr, $htmlContent);
                }

                /* icons for deleted and hidden */
                if (isset($row['deleted']) && $row['deleted'] == 1) {
                    $this->mmFormat->addIcon($aTtContentNode, 'button_cancel');
                }
                if (isset($row['hidden']) && $row['hidden'] == 1) {
                    $this->mmFormat->addIcon($aTtContentNode, 'closed');
                }
            }
            /* endwhile while($row */
            $GLOBALS['TYPO3_DB']->sql_free_result($result);
        } /* endif $dbCount */
    }

    /* </_getTTContentGroup> */

    /**
     * gets the icon and text for the corresponding CType in the tt_content table
     * @param array $ttContentElementRow
     * @return array
     */
    private function _ttcGetCTypeInfo($ttContentElementRow)
    {
        GLOBAL $TCA;

        $time = time();
        $return = array();
        $systemIconPrefixPath = 'typo3/sysext/t3skin/icons/gfx/';
        foreach ($TCA['tt_content']['columns']['CType']['config']['items'] as $ct) {
            if ($ct[1] == $ttContentElementRow['CType']) {

                if (isset($this->_iconCache[$ct[2]])) {
                    return $this->_iconCache[$ct[2]];
                }

                $icon = $systemIconPrefixPath . $ct[2];
                if (stristr($ct[2], 'EXT:') !== false) {
                    $extPathArray = explode(DIRECTORY_SEPARATOR, $ct[2]);
                    $_EXTKEY = str_replace('EXT:', '', $extPathArray[0]);
                    $extSiteRelPath = t3lib_extMgm::siteRelPath($_EXTKEY);
                    $icon = str_replace('EXT:' . $_EXTKEY . '/', $extSiteRelPath, $ct[2]);
                }

                $return = array(
                    'icon' => $icon,
                    'txt' => $GLOBALS['LANG']->sL($ct[0]),
                );
                $this->_iconCache[$ct[2]] = $return;
                break;
            }
        }

        /* change icon if hidden */
        $imgFileExt = '.gif';
        $hasTime = (!empty($ttContentElementRow['starttime']) && $ttContentElementRow['starttime'] >= $time) || !empty($ttContentElementRow['endtime']);
        $hasFeGroup = !empty($ttContentElementRow['fe_group']);

        if ($hasTime && !$hasFeGroup) {
            $return['icon'] = str_replace($imgFileExt, '__f' . $imgFileExt, $return['icon']);
        } elseif (!$hasTime && $hasFeGroup) {
            $return['icon'] = str_replace($imgFileExt, '__u' . $imgFileExt, $return['icon']);
        } elseif ($hasTime && $hasFeGroup) {
            $return['icon'] = str_replace($imgFileExt, '__hfu' . $imgFileExt, $return['icon']);
        }

        /* if hidden, then other options are unnecessary */
        if ($ttContentElementRow['hidden'] == 1 || $ttContentElementRow['deleted'] == 1) {
            $return['icon'] = str_replace($imgFileExt, '__h' . $imgFileExt, $return['icon']);
        }


        foreach ($TCA['tt_content']['columns']['list_type']['config']['items'] as $ct) {
            if (!empty($ct[0]) && $ct[1] == $ttContentElementRow['list_type']) {

                $icon = str_replace('../', '', $ct[2]);
                $return['icon'] = $icon;
                $return['pluginTxt'] = $GLOBALS['LANG']->sL($ct[0]);
                break;
            }
        }

        /* @todo if set starttime, endtime or hidden or deleted ... change icon ...
        see folder /typo3/sysext/t3skin/icons/gfx/i/ for more icons
         */
        return $return;
    }

    /* </_ttcGetCTypeInfo> */

    /**
     * loads some basic infos from table tx_templavoila_tmplobj
     * @param none
     * @return false if already loaded and true if just loaded
     */
    private function loadDatabaseTable($tablename, $columns = array())
    {

        if (count($this->dbTables[$tablename]) > 0) {
            return false;
        }

        $columns = array_merge($columns, array('uid'));

        $queryParts = array(
            'SELECT' => implode(',', $columns),
            'FROM' => $tablename,
            'WHERE' => '',
            'GROUPBY' => '',
            'ORDERBY' => '',
            'LIMIT' => ''
        );

        $result = $GLOBALS['TYPO3_DB']->exec_SELECT_queryArray($queryParts) or
            die('Please fix this error!<br>' . __FILE__ . ' Line ' . __LINE__ . ":\n<br>\n" . mysql_error() . "<hr>" . var_export($queryParts, 1));

        while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result)) {
            $this->dbTables[$tablename][$row['uid']] = $row;
        }
        return true;
    } /* </loadDatabaseTable> */


    /**
     * @param string $functionName
     * @return boolean
     */
    protected function mainNodeIsDisabled($functionName)
    {

        if (empty($this->settings['disabledNodeFunctions'])) {
            return false;
        }
        return stristr($this->settings['disabledNodeFunctions'], $functionName) === false ? false : true;

    }
}