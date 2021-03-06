<?php

/*
 * Copyright (C) 2015 Bibliotheks-Service Zentrum, Konstanz, Germany
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */
namespace Bsz\RecordTab;

use Vu;
use VuFind\RecordTab\AbstractBase;
use VuFind\Search\SearchRunner;
use VuFind\Search\Solr\Results;

/**
 * Class Articles
 * @package Bsz\RecordTab
 * @category boss
 * @author Cornelius Amzar <cornelius.amzar@bsz-bw.de>
 */
class Articles extends AbstractBase
{
    /**
     *
     * @var Vu
     */
    protected $runner;

    /**
     *
     * @var array
     */
    protected $content;

    /**
     * @var string
     */
    protected $searchClassId;

    protected $isils;

    /**
     * Constructor
     * @param SearchRunner $runner
     */
    public function __construct(SearchRunner $runner, $isils = [])
    {
        $this->runner = $runner;
        $this->isils = $isils;
        $this->accessPermission = 'access.ArticlesViewTab';
    }

    /**
     * Get the on-screen description for this tab
     * @return string
     */
    public function getDescription()
    {
        return 'Articles';
    }

    /**
     *
     * @return array|null
     */
    public function getContent()
    {
        if ($this->content === null) {
            $relId = $this->driver->tryMethod('getIdsRelatedArticle');
            $relId[] = $this->driver->getUniqueId();
            $this->content = [];
            if (is_array($relId) && count($relId) > 0) {
                foreach ($relId as $k => $id) {
                    $relId[$k] = 'id_related:"' . $id . '"';
                }

                $params = [
                    'sort' => 'publish_date_sort desc, id desc',
                    'lookfor' => implode(' OR ', $relId),
                    'limit' => 500
                ];

                $filter = [];
                if ($this->isFL() === false) {
                    foreach ($this->isils as $isil) {
                        $filter[] = '~institution_id:' . $isil;
                    }
                }
                $filter[] = 'material_content_type:Article';
                $params['filter'] = $filter;
                $results = $this->runner->run($params);

                $results instanceof Results;
                $this->content = $results->getResults();
            }
        }
        return $this->content;
    }

    /**
     * Check if we are in an interlending or ZDB-TAB
     *      **/
    public function isFL()
    {
        $last = '';
        if (isset($_SESSION['Search']['last'])) {
            $last = urldecode($_SESSION['Search']['last']);
        }
        if (strpos($last, 'consortium:FL') !== false
            || strpos($last, 'consortium:"FL"') !== false
            || strpos($last, 'consortium:ZDB') !== false
            || strpos($last, 'consortium:"ZDB"') !== false
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * This Tab is Active for collections or parts of collections only.
     * @return boolean
     */
    public function isActive()
    {
        $this->getContent();
        if (parent::isActive() && !empty($this->content)) {
            return true;
        }
        return false;
    }

    /**
     * @param $searchClassId
     */
    public function setSearchClassId($searchClassId)
    {
        if (isset($searchClassId) && !empty($searchClassId)) {
            $this->searchClassId = $searchClassId;
        }
    }
}
