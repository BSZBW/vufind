<?php
/**
 * Alma ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2017.
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
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace VuFind\ILS\Driver;

/**
 * Alma ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Alma extends AbstractBase implements \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Alma API base URL.
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Alma API key.
     *
     * @var string
     */
    protected $apiKey;

    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
    public function init()
    {
        if (empty($this->config)) {
            throw new ILSException('Configuration needs to be set.');
        }
        $this->baseUrl = $this->config['Catalog']['apiBaseUrl'];
        $this->apiKey = $this->config['Catalog']['apiKey'];
    }

    /**
     * Make an HTTP request against Alma
     *
     * @param string $path   Path to retrieve from API (excluding base URL/API key)
     * @param string $params Additional GET params
     *
     * @return \SimpleXMLElement
     */
    protected function makeRequest($path, $params = [])
    {
        // TODO: Support requests of different methods
        if (!isset($params['apiKey'])) {
            $params['apiKey'] = $this->apiKey;
        }
        $client = $this->httpService->createClient($this->baseUrl . $path);
        $client->setParameterGet($params);
        $result = $client->send();
        if ($result->isSuccess()) {
            return simplexml_load_string($result->getBody());
        } else {
            // TODO: Throw an error
            error_log($this->baseUrl . $path);
            error_log($result->getBody());
        }
        return null;
    }

    /**
     * Given an item, return the availability status.
     *
     * @param \SimpleXMLElement $item Item data
     *
     * @return bool
     */
    protected function getAvailabilityFromItem($item)
    {
        return (string)$item->item_data->base_status === '1';
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, array $patron = null)
    {
        $results = [];
        $copyCount = 0;
        $bibPath = '/bibs/' . urlencode($id) . '/holdings';
        if ($holdings = $this->makeRequest($bibPath)) {
            foreach ($holdings->holding as $holding) {
                $holdingId = (string)$holding->holding_id;
                $itemPath = $bibPath . '/' . urlencode($holdingId) . '/items';
                if ($currentItems = $this->makeRequest($itemPath)) {
                    foreach ($currentItems->item as $item) {
                        $barcode = (string)$item->item_data->barcode;
                        $results[] = [
                            'id' => $id,
                            'source' => 'Solr',
                            'availability' => $this->getAvailabilityFromItem($item),
                            'status' => (string)$item->item_data->base_status[0]
                                ->attributes()['desc'],
                            'location' => (string)$holding->library[0]
                                ->attributes()['desc'],
                            'reserve' => 'N',   // TODO: support reserve status
                            'callnumber' => (string)$item->holding_data->call_number,
                            'duedate' => null, // TODO: support due dates
                            'returnDate' => false, // TODO: support recent returns
                            'number' => ++$copyCount,
                            'barcode' => empty($barcode) ? 'n/a' : $barcode,
                            'item_id' => (string)$item->item_data->pid,
                        ];
                    }
                }
            }
        }
        return $results;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $barcode  The patron barcode
     * @param string $password The patron password
     *
     * @throws ILSException
     * @return mixed           Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($barcode, $password)
    {
        $client = $this->httpService->createClient(
            $this->baseUrl . '/users/' . $barcode
            . '?apiKey=' . urlencode($this->apiKey)
            . '&op=auth&password=' . urlencode(trim($password))
        );
        $client->setMethod(\Zend\Http\Request::METHOD_POST);
        $response = $client->send();
        // Test once we have POST access
        if ($response->isSuccess()) {
            return [
                'cat_username' => trim($barcode),
                'cat_password' => trim($password)
            ];
        }
        return null;
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @return array Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        $xml = $this->makeRequest('/users/' . $patron['cat_username']);
        if (empty($xml)) {
            return [];
        }
        $profile = [
            'firstname' => $xml->first_name,
            'lastname'  => $xml->last_name,
            'group'     => $xml->user_group['desc']
        ];
        $contact = $xml->contact_info;
        if ($contact) {
            if ($contact->addresses) {
                $address = $contact->addresses[0]->address;
                $profile['address1'] = $address->line1;
                $profile['address2'] = $address->line2;
                $profile['address3'] = $address->line3;
                $profile['zip']      = $address->postal_code;
                $profile['city']     = $address->city;
                $profile['country']  = $address->country;
            }
            if ($contact->phones) {
                $profile['phone'] = $contact->phones[0]->phone->phone_number;
            }
        }
        return $profile;
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's fines on success.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getMyFines($patron)
    {
        $xml = $this->makeRequest(
            '/users/' . $patron['cat_username'] . '/fees'
        );
        $fineList = [];
        for ($i = 0; $i < count($xml->fees); $i++) {
            $fineList[] = [
                "amount"   => $xml->fees[$i]->original_amount,
                "balance"  => $xml->fees[$i]->balance,
                "checkout" => $this->dateConverter->convertToDisplayDate(
                    'U', $checkout
                ),
                "fine"     => $xml->fees[$i]->type['desc']
            ];
        }
        return $fineList;
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's holds on success.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getMyHolds($patron)
    {
        $xml = $this->makeRequest(
            '/users/' . $patron['cat_username'] . '/requests',
            ['request_type' => 'HOLD']
        );
        $holdList = [];
        for ($i = 0; $i < count($xml->user_requests); $i++) {
            $request = $xml->user_requests[$i];
            $holdList[] = [
                'create' => $request->request_date,
                'expire' => $request->last_interest_date,
                'id' => $request->request_id,
                'in_transit' => $request->request_status !== 'IN_PROCESS',
                'item_id' => $request->mms_id,
                'location' => $request->pickup_location,
                'processed' => $request->item_policy === 'InterlibraryLoan'
                    && $request->request_status !== 'NOT_STARTED',
                'title' => $request->title,
                /*
                // VuFind keys
                'available'         => $request->,
                'canceled'          => $request->,
                'institution_dbkey' => $request->,
                'institution_id'    => $request->,
                'institution_name'  => $request->,
                'position'          => $request->,
                'reqnum'            => $request->,
                'requestGroup'      => $request->,
                'source'            => $request->,
                // Alma keys
                "author": null,
                "comment": null,
                "desc": "Book"
                "description": null,
                "material_type": {
                "pickup_location": "Burns",
                "pickup_location_library": "BURNS",
                "pickup_location_type": "LIBRARY",
                "place_in_queue": 1,
                "request_date": "2013-11-12Z"
                "request_id": "83013520000121",
                "request_status": "NOT_STARTED",
                "request_type": "HOLD",
                "title": "Test title",
                "value": "BK",
                */
            ];
        }
        return $holdList;
    }

    /**
     * Get Patron Storage Retrieval Requests
     *
     * This is responsible for retrieving all call slips by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's holds
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getMyStorageRetrievalRequests($patron)
    {
        $xml = $this->makeRequest(
            '/users/' . $patron['cat_username'] . '/requests',
            ['request_type' => 'MOVE']
        );
        $holdList = [];
        for ($i = 0; $i < count($xml->user_requests); $i++) {
            $request = $xml->user_requests[$i];
            if (!isset($request->item_policy)
                || $request->item_policy !== 'Archive'
            ) {
                continue;
            }
            $holdList[] = [
                'create' => $request->request_date,
                'expire' => $request->last_interest_date,
                'id' => $request->request_id,
                'in_transit' => $request->request_status !== 'IN_PROCESS',
                'item_id' => $request->mms_id,
                'location' => $request->pickup_location,
                'processed' => $request->item_policy === 'InterlibraryLoan'
                    && $request->request_status !== 'NOT_STARTED',
                'title' => $request->title,
            ];
        }
        return $holdList;
    }

    /**
     * Get Patron ILL Requests
     *
     * This is responsible for retrieving all ILL requests by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's ILL requests
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getMyILLRequests($patron)
    {
        $xml = $this->makeRequest(
            '/users/' . $patron['cat_username'] . '/requests',
            ['request_type' => 'MOVE']
        );
        $holdList = [];
        for ($i = 0; $i < count($xml->user_requests); $i++) {
            $request = $xml->user_requests[$i];
            if (!isset($request->item_policy)
                || $request->item_policy !== 'InterlibraryLoan'
            ) {
                continue;
            }
            $holdList[] = [
                'create' => $request->request_date,
                'expire' => $request->last_interest_date,
                'id' => $request->request_id,
                'in_transit' => $request->request_status !== 'IN_PROCESS',
                'item_id' => $request->mms_id,
                'location' => $request->pickup_location,
                'processed' => $request->item_policy === 'InterlibraryLoan'
                    && $request->request_status !== 'NOT_STARTED',
                'title' => $request->title,
            ];
        }
        return $holdList;
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        return $this->getHolding($id);
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @return array An array of getStatus() return values on success.
     */
    public function getStatuses($ids)
    {
        $results = [];
        $copyCount = 0;
        $params = [
            'mms_id' => implode(',', $ids),
            'view'   => 'brief', // no solrmarc xml
            // 'expand' => 'p_avail,e_avail,d_avail'
        ];
        if ($bibs = $this->makeRequest('/bibs', $params)) {
            foreach ($bibs->holding as $bib) {
                if (!isset($bib->holdings->link)) {
                    continue;
                }
                $bibPath = $bib->holdings->link;
                $holdings = $this->makeRequest($bibPath);
                foreach ($holdings->holding as $holding) {
                    $holdingId = (string)$holding->holding_id;
                    $itemPath = $bibPath . '/' . urlencode($holdingId) . '/items';
                    if ($currentItems = $this->makeRequest($itemPath)) {
                        foreach ($currentItems->item as $item) {
                            $availability = $this->getAvailabilityFromItem($item);
                            $barcode = (string)$item->item_data->barcode;
                            $callnumber = (string)$item->holding_data->call_number;
                            $results[] = [
                                'id' => $id,
                                'source' => 'Solr',
                                'availability' => $availability,
                                'status' => (string)$item->item_data->base_status[0]
                                    ->attributes()['desc'],
                                'location' => (string)$holding->library[0]
                                    ->attributes()['desc'],
                                'reserve' => 'N',   // TODO: support reserve status
                                'callnumber' => $callnumber,
                                'duedate' => null, // TODO: support due dates
                                'returnDate' => false,// TODO: support recent returns
                                'number' => ++$copyCount,
                                'barcode' => empty($barcode) ? 'n/a' : $barcode,
                                'item_id' => (string)$item->item_data->pid,
                            ];
                        }
                    }
                }
            }
        }
        return $results;
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @return array     An array with the acquisitions data on success.
     */
    public function getPurchaseHistory($id)
    {
        $issues = rand(0, 3);
        $retval = [];
        for ($i = 0; $i < $issues; $i++) {
            $retval[] = ['issue' => 'issue ' . ($i + 1)];
        }
        return $retval;
    }


    /**
     * @param array $holdDetails An associative array w/ atleast patron and item_id
     *
     * @return array success: bool, sysMessage: string
     */
    public function placeHold($holdDetails)
    {
        // https://developers.exlibrisgroup.com/alma/apis/users
        // POST /almaws/v1/users/{user_id}/requests
        $client = $this->httpService->createClient(
            $this->baseUrl . '/users/' . $holdDetails['patron']['cat_username']
            . '/requests?apiKey=' . urlencode($this->apiKey)
            . '&item_pid=' . urlencode($holdDetails['item_id'])
        );
        $client->setMethod(\Zend\Http\Request::METHOD_POST);
        $response = $client->send();
        // Test once we have POST access
        if ($response->isSuccess()) {
            return ['success' => true];
        }
        $xml = simplexml_load_string($response->getBody());
        return [
            'success' => false,
            'system_msg' => $xml->errorList[0]->errorMessage // TODO: This is a guess
        ];
    }
    /**
     * @param string $courseID     Value from getCourses
     * @param string $instructorID Value from getInstructors
     * @param string $departmentID Value from getDepartments
     *
     * @return array With key BIB_ID - The record ID of the current reserve item.
     *               Not currently used:
     *               DISPLAY_CALL_NO, AUTHOR, TITLE, PUBLISHER, PUBLISHER_DATE
     * /
    public function findReserves($courseID, $instructorID, $departmentID) {
        // https://developers.exlibrisgroup.com/alma/apis/courses
        // GET /​almaws/​v1/​courses/​{course_id}/​reading-lists
    } //*/

    /**
     * @return array with key = course ID, value = course name
     * /
    public function getCourses() {
        // https://developers.exlibrisgroup.com/alma/apis/courses
        // GET /​almaws/​v1/​courses
    } //*/

    /**
     * @return array with key = course ID, value = course name
     * /
    public function getFunds() {
        // https://developers.exlibrisgroup.com/alma/apis/acq
        // GET /​almaws/​v1/​acq/​funds
    } //*/

    /**
     * @param string $bibID Bibligraphic ID
     *
     * @return boolean
     * /
    public function hasHoldings($bibID) {
        // https://developers.exlibrisgroup.com/alma/apis/bibs
        // GET /almaws/v1/bibs/{mms_id}/holdings
    } //*/

    /* ================= METHODS INACCESSIBLE OUTSIDE OF GET ===================== */

    /**
     * @param array $cancelDetails An associative array with two keys:
     *                  patron  (array returned by the driver's patronLogin method)
     *                  details (array returned by the driver's getCancelHoldDetails)
     *
     * @return array count – The number of items successfully cancelled
     *               items – Associative array where keyed by item_id (getMyHolds)
     *                   success – Boolean true or false
     *                   status – A status message from the language file (required)
     *                   sysMessage - A system supplied failure message (optional)
     * /
    public function cancelHolds($cancelDetails) {
        // https://developers.exlibrisgroup.com/alma/apis/users
        // DELETE /almaws/v1/users/{user_id}/requests/{request_id}
    } //*/
}
