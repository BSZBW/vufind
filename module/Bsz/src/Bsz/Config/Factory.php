<?php

/*
 * The MIT License
 *
 * Copyright 2016 Cornelius Amzar <cornelius.amzar@bsz-bw.de>.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace Bsz\Config;

use Zend\ServiceManager\ServiceManager,
    Zend\Db\ResultSet\ResultSet,
    \Zend\Db\TableGateway\TableGateway;

/**
 * Description of Factory
 *
 * @author Cornelius Amzar <cornelius.amzar@bsz-bw.de>
 */
class Factory
{

    /**
     * 
     * @param ServiceManager $sm
     * @return \Bsz\Config\Client
     */
    public static function getClient(ServiceManager $sm)
    {
        $vufindconf = $sm->get('VuFind\Config')->get('config')->toArray();
        $bszconf = $sm->get('VuFind\Config')->get('bsz')->toArray();
        $client = new Client(array_merge($vufindconf, $bszconf), true);
//        if ($client->isIsilSession()) {
//            $libraries = $sm->get('bsz\libraries');
//            $request = $sm->get('Request');
//            $client->setLibraries($libraries);
//            $client->setRequest($request);
//        }
        return $client;
    }

    /**
     * 
     * @param ServiceManager $sm
     * @return \Bsz\Interlending
     */
    public static function getLibrariesTable(ServiceManager $sm)
    {
        # fetch mysql connection info out config
        $config = $sm->get('VuFind\Config')->get('config');
        $adapter = $sm->get('\VuFind\DbAdapterFactory')
            ->getAdapterFromConnectionString($config->get('Database')->get('db_libraries'));
        $resultSetPrototype = new ResultSet(ResultSet::TYPE_ARRAYOBJECT, new Library());
        $librariesTable = new Libraries('libraries', $adapter, null, $resultSetPrototype);
        return $librariesTable;
    }   
    
}

