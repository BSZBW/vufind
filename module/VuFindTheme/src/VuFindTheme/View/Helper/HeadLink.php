<?php
/**
 * Head link view helper (extended for VuFind's theme system)
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFindTheme\View\Helper;

/**
 * Head link view helper (extended for VuFind's theme system)
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class HeadLink extends \Zend\View\Helper\HeadLink
{
    /**
     * Theme information service
     *
     * @var \VuFindTheme\ThemeInfo
     */
    protected $themeInfo;

    /**
     * Constructor
     *
     * @param \VuFindTheme\ThemeInfo $themeInfo Theme information service
     */
    public function __construct(\VuFindTheme\ThemeInfo $themeInfo)
    {
        parent::__construct();
        $this->themeInfo = $themeInfo;
    }

    /**
     * Create HTML link element from data item
     *
     * @param \stdClass $item data item
     *
     * @return string
     */
    public function itemToString(\stdClass $item)
    {
        // Normalize href to account for themes, then call the parent class:
        $relPath = 'css/' . $item->href;
        $currentTheme = $this->themeInfo->findContainingTheme($relPath);

        if (!empty($currentTheme)) {
            $urlHelper = $this->getView()->plugin('url');
            $item->href = $urlHelper('home') . "themes/$currentTheme/" . $relPath;
        }

        return parent::itemToString($item);
    }

    /**
     * Compile a less file to css and add to css folder
     *
     * @param string $file path to less file
     *
     * @return void
     */
    public function addLessStylesheet($file)
    {
        $relPath = 'less/' . $file;
        $urlHelper = $this->getView()->plugin('url');
        $currentTheme = $this->themeInfo->findContainingTheme($relPath);
        $home = APPLICATION_PATH . "/themes/$currentTheme/";
        $cssDirectory = $urlHelper('home') . "themes/$currentTheme/css/less/";
        $cacheDirectory = $home . 'css/less/';
        list($fileName, ) = explode('.', $file);
        $inputFile  = $home . $relPath;

        $time = microtime(true);
        $css = false;
        try {
            $less_files = array(
                APPLICATION_PATH . '/themes/' . $currentTheme . '/' . $relPath
                    => $cssDirectory
            );
            $css_file_name = \Less_Cache::Get(
                $less_files,
                array('cache_dir' => $cacheDirectory, 'compress' => true)
            );
            $this->prependStylesheet($cssDirectory . $css_file_name);
        } catch (\Exception $e) {
            error_log($e);
            $this->prependStylesheet($cssDirectory . $fileName . '.css');
        }
    }

    /**
     * Compile a scss file to css and add to css folder
     *
     * @param string $file path to scss file
     *
     * @return void
     */
    public function addSassStylesheet($file)
    {
        $relPath = 'scss/' . $file;
        $themeParents = array_keys($this->themeInfo->getThemeInfo());
        $currentTheme = $themeParents[0];
        $home = APPLICATION_PATH . "/themes/$currentTheme/";
        list($fileName, ) = explode('.', $file);
        $outputFile = $home . 'css/scss/' . $fileName . '.css';
        $urlHelper = $this->getView()->plugin('url');

        $scss = new \scssc();
        $paths = array();
        foreach ($themeParents as $theme) {
            $paths[] = APPLICATION_PATH . '/themes/' . $theme . '/scss/';
        }
        $scss->setImportPaths($paths);
        $scss->setFormatter('scss_formatter_compressed');
        $css = $scss->compile('@import "' . $file . '"');
        $int = file_put_contents($outputFile, $css);
        $this->prependStylesheet($urlHelper('home') . "themes/$currentTheme/css/scss/" . $fileName . '.css');
    }
}
