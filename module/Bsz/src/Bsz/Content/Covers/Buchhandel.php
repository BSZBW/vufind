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
namespace Bsz\Content\Covers;
/**
 * Class for the new Buchhandel API (mandatory in 2016)
 * Buchhandel requires to have a link to the article on your page! 
 * This is not possible with original VuFind at the moment so use at own risk. 
 * 
 * In the past this service was very slow from time to time. 
 * This got much better with a large cache. 
 *
 * @author Cornelius Amzar <cornelius.amzar@bsz-bw.de>
 */
class Buchhandel extends \VuFind\Content\AbstractCover {
    
    const BUCHHANDEL_URL = 'https://api.vlb.de/api/v1/cover/%s/%s?access_token=%s';
    /**
     * Your Buchhandel Bearer
     */
    const BEARER = '9fb6de22-8def-47f3-8aac-42f7bccc2b0e';
    
    /**
     * Needed for authorization
     * type string
     */
    protected $bearer;
    protected $supportsEan = true;
    
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->supportsIsbn = true;
        $this->cacheAllowed = true;
     
    }
     /**
     * Get image URL for a particular API key and set of IDs (or false if invalid).
     *
     * @param string $key  API key
     * @param string $size Size of image to load (small/medium/large)
     * @param array  $ids  Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object and 'issn' pointing to a string)
     *
     * @return string|bool
     */
    public function getUrl($key, $size, $ids)
    {
        if (!isset($ids['isbn'])) {
            return false;
        }
        if (isset($ids['isbn'])) {
            $id = $ids['isbn']->get13(); 
        } 
                
        // Convert internal size value to Buchhandel equivalent:
        switch (strtolower(trim($_GET['size']))) {
            case 'large':
                $size = 'l';
                break;
            case 'medium':
                $size = 'm';
                break;
            case 'small':
                $size = 's';
                break;
            default:
                $size = 'm';
                break;
        }
        $url = sprintf(static::BUCHHANDEL_URL, $id, $size, static::BEARER);
        return $url;
    }  
    

    
}
