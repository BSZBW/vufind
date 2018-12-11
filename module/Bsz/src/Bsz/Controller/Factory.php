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

namespace Bsz\Controller;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for all controllers which needs params
 *
 * @author Cornelius Amzar <cornelius.amzar@bsz-bw.de>
 */
class Factory extends \VuFind\Controller\GenericFactory {

     /**
     * Construct the RecordController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return RecordController
     */
    public static function getRecordController(ServiceManager $sm)
    {
        return new RecordController(
            $sm->getServiceLocator(),
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }
    /**
     * 
     * @param ServiceManager $sm
     * @return \Bsz\Controller\SearchController
     */
    public static function getSearchController(ServiceManager $sm)
    {
        return new SearchController(
            $sm->getServiceLocator(),
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }
    /**
     * 
     * @param ServiceManager $sm
     * @return \Bsz\Controller\TestController
     */
    public static function getTestController(ServiceManager $sm) 
    {
        return new TestController(
            $sm->getServiceLocator()->get('Bsz\Config\Libraries')
        );
    }
    /**
     * 
     * @param ServiceManager $sm
     * @return \Bsz\Controller\BszController
     */
    public static function getBszController(ServiceManager $sm)
    {
        return new BszController($sm->getServiceLocator());
    }
    
   
}

