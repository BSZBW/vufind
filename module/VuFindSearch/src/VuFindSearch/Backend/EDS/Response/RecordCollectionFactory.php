<?php
/**
 * Factory for record collection.
 *
 * PHP version 5
 *
 * Copyright (C) EBSCO Industries 2013
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindSearch\Backend\EDS\Response;

use VuFindSearch\Exception\InvalidArgumentException;
use VuFindSearch\Response\RecordCollectionFactoryInterface;

/**
 * Factory for record collection.
 *
 * @category VuFind
 * @package  Search
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RecordCollectionFactory implements RecordCollectionFactoryInterface
{
    /**
     * Factory to turn data into a record object.
     *
     * @var Callable
     */
    protected $recordFactory;

    /**
     * Class of collection.
     *
     * @var string
     */
    protected $collectionClass;

    /**
     * Constructor.
     *
     * @param Callable $recordFactory   Record factory callback
     * @param string   $collectionClass Class of collection
     *
     * @return void
     */
    public function __construct($recordFactory = null, $collectionClass = null)
    {
        if (!is_callable($recordFactory)) {
            throw new InvalidArgumentException('Record factory must be callable.');
        }
        $this->recordFactory = $recordFactory;
        $this->collectionClass = (null === $collectionClass)
            ? 'VuFindSearch\Backend\EDS\Response\RecordCollection'
            : $collectionClass;
    }

    /**
     * Return record collection.
     *
     * @param array $response EdsApi search response
     *
     * @return RecordCollection
     */
    public function factory($response)
    {
        if (!is_array($response)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unexpected type of value: Expected array, got %s',
                    gettype($response)
                )
            );
        }
        $collection = new $this->collectionClass($response);
        //obtain path to records
        $records = [];
        if (isset($response['SearchResult'])
            && isset($response['SearchResult']['Data'])
            && isset($response['SearchResult']['Data']['Records'])
        ) {
            // Format of the search response
            $records = $response['SearchResult']['Data']['Records'];
        } elseif (isset($response['Records'])) { // Format of the retrieve response
            $records = $response['Records'];
        }

        foreach ($records as $record) {
            $collection->add(call_user_func($this->recordFactory, $record));
        }
        return $collection;
    }
}
