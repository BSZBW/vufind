<?php
/**
 * Eds Controller
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Controller;

use EBSCO\EdsApi\Zend2 as EdsApi;
use VuFind\Solr\Utils as SolrUtils;
/**
 * EDS Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

class EdsController extends AbstractSearch
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->searchClassId = 'EDS';
        parent::__construct();
    }

    /**
     * Is the result scroller active?
     *
     * @return bool
     */
    protected function resultScrollerActive()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('EDS');
        return (isset($config->Record->next_prev_navigation)
            && $config->Record->next_prev_navigation);
    }

    /**
     * Handle an advanced search
     *
     * @return mixed
     */
    public function advancedAction()
    {
        // Standard setup from base class:
        $view = parent::advancedAction();
        // Set up facet information:
        $view->limiterList = $this->processAdvancedFacets(
            $this->getAdvancedFacets(), $view->saved
        );
        $view->expanderList = $this->processAdvancedExpanders($view->saved);
        $view->searchModes = $this->processAdvancedSearchModes($view->saved);
        $view->dateRangeLimit = $this->processPublicationDateRange($view->saved);
        return $view;
    }

    /**
     * Home action
     *
     * @return mixed
     */
    public function homeAction()
    {
        $this->setUp();
        return $this->createViewModel(
            array('results' => $this->getHomePageFacets())
        );
    }

    /**
     * Search action -- call standard results action
     *
     * @return mixed
     */
    public function searchAction()
    {
        return $this->resultsAction();
    }


    /**
     * Return a Search Results object containing advanced facet information.  This
     * data may come from the cache.
     *
     * @return \VuFind\Search\EDS\Results
     */
    protected function getAdvancedFacets()
    {
        // VuFind facets are what the EDS API calls limiters. Available limiters
        // are returned with a call to the EDS API Info method and are cached.
        // Since they are obtained from a separate call, there is no need to call
        // search.

        // Check if we have facet results stored in session. Build them if we don't.
        // pull them from the session cache
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('EDS');
        $results = $this->getResultsManager()->get('EDS');
        $params = $results->getParams();
        $options = $params->getOptions();
        $availableLimiters = $options->getAvailableLimiters();
        if (!$availableLimiters) {
            //execute a call to search just to pull in the limiters
            $this->setUp();

        }


        $limit = isset($config->Advanced_Facet_Settings->facet_limit)
                ? $config->Advanced_Facet_Settings->facet_limit : 100;
        //filter the available limtiers

        return $availableLimiters;
    }

    /**
     * Return a Search Results object containing homepage facet information.  This
     * data may come from the cache.
     *
     * @return \VuFind\Search\EDS\Results
     */
    protected function getHomePageFacets()
    {
        // For now, we'll use the same fields as the advanced search screen.
        return $this->getAdvancedFacets();
    }

    /**
     * Process the facets to be used as limits on the Advanced Search screen.
     *
     * @param array  $facetList    The advanced facet values
     * @param object $searchObject Saved search object (false if none)
     *
     * @return array               Sorted facets, with selected values flagged.
     */
    protected function processAdvancedFacets($facetList, $searchObject = false)
    {
        // Process the facets, assuming they came back
        foreach ($facetList as $facet => $list) {
            if (isset($list['LimiterValues'])) {
                foreach ($list['LimiterValues'] as $key => $value) {
                    // Build the filter string for the URL:
                    $fullFilter = $facet.':'.$value['Value'];

                    // If we haven't already found a selected facet and the current
                    // facet has been applied to the search, we should store it as
                    // the selected facet for the current control.
                    if ($searchObject) {
                        $limitFilt = 'LIMIT|'.$fullFilter;
                        if ($searchObject->getParams()->hasFilter($limitFilt)) {
                            $facetList[$facet]['LimiterValues'][$key]['selected']
                                = true;
                            // Remove the filter from the search object -- we don't
                            // want it to show up in the "applied filters" sidebar
                            // since it will already be accounted for by being
                            // selected in the filter select list!
                            $searchObject->getParams()->removeFilter($limitFilt);
                        }
                    } else {
                        if ('y' == $facetList[$facet]['DefaultOn']) {
                            $facetList[$facet]['selected'] = true;
                        }
                    }
                }
            }
        }
        return $facetList;
    }

    /**
     * Process the expanders to be used on the Advanced Search screen.
     *
     * @param object $searchObject Saved search object (false if none)
     *
     * @return array               Sorted facets, with selected values flagged.
     */
    protected function processAdvancedExpanders($searchObject = false)
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('EDS');
        $results = $this->getResultsManager()->get('EDS');
        $params = $results->getParams();
        $options = $params->getOptions();
        $availableExpanders = $options->getAvailableExpanders();
        $defaultExpanders = $options->getDefaultExpanders();
        // Process the expanders, assuming they came back
        foreach ($availableExpanders as $key => $value) {
            if ($searchObject) {
                $expandFilt = 'EXPAND:'.$value['Value'];
                if ($searchObject->getParams()->hasFilter($expandFilt)) {
                    $availableExpanders[$key]['selected'] = true;
                    // Remove the filter from the search object -- we don't want
                    // it to show up in the "applied filters" sidebar since it
                    // will already be accounted for by being selected in the
                    // filter select list!
                    $searchObject->getParams()->removeFilter($expandFilt);
                }
            } else {
                if (in_array($key, $defaultExpanders)) {
                    $availableExpanders[$key]['selected'] = true;
                }
            }
        }
        return $availableExpanders;
    }

    /**
     * Process the publicationd date range limiter widget
     *
     * @param object $searchObject Saved search object (false if none)
     *
     * @return array               To and from dates
     */
    protected function processPublicationDateRange($searchObject = false)
    {
        $from = $to = '';
        if ($searchObject) {
            $filters = $searchObject->getParams()->getFilterList();
            foreach ($filters as $key => $value) {
                if ('PublicationDate' == $key) {
                    if ($range = SolrUtils::parseRange($value[0]['value'])) {
                        $from = $range['from'] == '*' ? '11' : $range['from'];
                        $to = $range['to'] == '*' ? '12' : $range['to'];
                    }
                    $searchObject->getParams()
                        ->removeFilter($key.':'.$value[0]['value']);
                    break;
                }
            }
        }
        return array($from, $to);
    }

    /**
     * Process the search modes to be used on the Advanced Search screen.
     *
     * @param object $searchObject Saved search object (false if none)
     *
     * @return array               search modes with selected values flagged.
     */
    protected function processAdvancedSearchModes($searchObject = false)
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('EDS');
        $results = $this->getResultsManager()->get('EDS');
        $params = $results->getParams();
        $options = $params->getOptions();
        $searchModes = $options->getModeOptions();
        // Process the facets, assuming they came back
        foreach ($searchModes as $key => $mode) {
            if ($searchObject) {
                $modeFilter = 'SEARCHMODE:'.$mode['Value'];
                if ($searchObject->getParams()->hasFilter($modeFilter)) {
                    $searchModes[$key]['selected'] = true;
                    // Remove the filter from the search object -- we don't want
                    // it to show up in the "applied filters" sidebar since it
                    // will already be accounted for by being selected in the
                    // filter select list!
                    $searchObject->getParams()->removeFilter($modeFilter);
                }
            } else {
                if ($key == $options->getDefaultMode()) {
                    $searchModes[$key]['selected'] = true;
                }
            }
        }

        return $searchModes;
    }

    /**
     * Make the initial calls to the EDS API to obtain/generate authentication and
     * session tokens as well as calling the info method to cache search criteria
     *
     * @return void
     */
    public function setUp()
    {
        $results = $this->getResultsManager()->get($this->searchClassId);
        $params = $results->getParams();
        $params->isSetupOnly = true;

        // Attempt to perform the search; if there is a problem, inspect any Solr
        // exceptions to see if we should communicate to the user about them.
        try {
            // Explicitly execute search within controller -- this allows us to
            // catch exceptions more reliably:
            $results->performAndProcessSearch();


        } catch (\VuFindSearch\Backend\Exception\BackendException $e) {
            if ($e->hasTag('VuFind\Search\ParserError')) {
                // If it's a parse error or the user specified an invalid field, we
                // should display an appropriate message:
                $view->parseError = true;

                // We need to create and process an "empty results" object to
                // ensure that recommendation modules and templates behave
                // properly when displaying the error message.
                $view->results = $this->getResultsManager()->get('EmptySet');
                $view->results->setParams($params);
                $view->results->performAndProcessSearch();
            } else {
                throw $e;
            }
        }
    }
}

