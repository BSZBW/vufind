<?php
/**
 * finc specific model for MARC records with a fullrecord in Solr.
 *
 * PHP version 5
 *
 * Copyright (C) Leipzig University Library 2015.
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
 * @package  RecordDrivers
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @author   Gregor Gawol <gawol@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finc\RecordDriver;

use Bsz\RecordDriver\HelperTrait;
use VuFindSearch\Query\Query as Query;

/**
 * finc specific model for MARC records with a fullrecord in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @author   Gregor Gawol <gawol@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
trait SolrMarcFincTrait
{
    use HelperTrait;
    /**
     * Returns true if the record supports real-time AJAX status lookups.
     *
     * @return bool
     */
    public function supportsAjaxStatus()
    {
        return $this->hasILS();
    }

    /**
     * Returns whether the current record is a RDA record (contains string 'rda'
     * in 040$e)
     *
     * @return bool
     */
    public function isRDA()
    {
        return $this->getFirstFieldValue('040', ['e']) == 'rda';
    }

    /**
     * Return an array of associative URL arrays with one or more of the following
     * keys:
     *
     * <li>
     *   <ul>desc: URL description text to display (optional)</ul>
     *   <ul>url: fully-formed URL (required if 'route' is absent)</ul>
     *   <ul>route: VuFind route to build URL with (required if 'url' is absent)</ul>
     *   <ul>routeParams: Parameters for route (optional)</ul>
     *   <ul>queryString: Query params to append after building route (optional)</ul>
     * </li>
     *
     * @return array
     */
    public function getURLs()
    {
        $retVal = [];

        // Which fields/subfields should we check for URLs?
        $fieldsToCheck = [
            '856' => ['u'],   // Standard URL
            '555' => ['a']         // Cumulative index/finding aids
        ];

        foreach ($fieldsToCheck as $field => $subfields) {
            $urls = $this->getMarcRecord()->getFields($field);
            if ($urls) {
                foreach ($urls as $url) {
                    $isil = $url->getSubfield('9');
                    $indicator1 = $url->getIndicator('1');
                    $indicator2 = $url->getIndicator('2');

                    $isISIL = false;

                    if ($isil) {
                        $isil = $isil->getData();
                        if (true === in_array($isil, $this->isil)) {
                            $isISIL = true;
                        }
                    } elseif (!$this->_isEBLRecord()) {
                        $isISIL = true;
                    }

                    if ($isISIL) {
                        // Is there an address in the current field?
                        $address = $url->getSubfield('u');
                        if ($address) {
                            $address = $address->getData();

                            $tmpArr = [];
                            // Is there a description?  If not, just use the URL
                            // itself.
                            foreach (['y', '3', 'z', 'x'] as $current) {
                                $desc = $url->getSubfield($current);
                                if ($desc) {
                                    $desc = $desc->getData();
                                    $tmpArr[] = $desc;
                                }
                            }
                            $tmpArr = array_unique($tmpArr);
                            $desc = implode(', ', $tmpArr);

                            // If no description take url as description
                            // For 856[40] url denoting resource itself
                            // use "Online Access"/"Online-Zugang" #6109
                            if (empty($desc)) {
                                if ($indicator1 == 4
                                    && $indicator2 == 0
                                    && preg_match('!https?://.*?doi.org/!', $address)
                                ) {
                                    $desc = "Online Access";
                                } else {
                                    $desc = $address;
                                }
                            }

                            // If url doesn't exist as key so far write
                            // to return variable.
                            if (!in_array(
                                ['url' => $address, 'desc' => $desc], $retVal
                            )
                            ) {
                                $retVal[] = ['url' => $address, 'desc' => $desc];
                            }
                        }
                    }
                }
            }
        }
        return $retVal;
    }

    /**
     * Checks if the record is an EBL record (as defined in config.ini section
     * [Ebl]->product_sigel). Refs #8055 #9634
     *
     * @return bool
     */
    private function _isEBLRecord()
    {
        $values = $this->getFieldArray('912', ['a']);
        if (isset($this->mainConfig->Ebl->product_sigel)) {
            if (is_object($this->mainConfig->Ebl->product_sigel)) {
                // handle product_sigel array
                return (
                    count(
                        array_intersect(
                            $values, $this->mainConfig->Ebl->product_sigel->toArray()
                        )
                    ) > 0
                ) ? true : false;
            } else {
                // handle single product_sigel (legacy support)
                return (
                    0 < in_array(
                        $this->mainConfig->Ebl->product_sigel,
                        $values
                    )
                ) ? true : false;
            }
        }
        return false;
    }

    /**
     * Method to return the order information stored in fullrecord
     * LocalMarcFieldOfLibrary $m
     *
     * @return null|string
     */
    public function getLocalOrderInformation()
    {
        // loop through all existing LocalMarcFieldOfLibrary
        if ($fields = $this->getMarcRecord()->getFields(
            $this->getLocalMarcFieldOfLibrary())
        ) {
            foreach ($fields as $field) {
                // return the first occurance of $m
                if ($field->getSubfield('m')) {
                    return $field->getSubfield('m')->getData();
                }
            }
        }
        // no LocalMarcFieldOfLibrary or $m found
        return null;
    }

    /**
     * Return the local callnumber. Refs #2639
     *
     * @todo Optimization by removing of prefixed isils
     *
     * @return array   Return fields.
     * @deprecated (Cmp. refs #6324)
     */
    public function getLocalCallnumber()
    {
        $array = [];

        if (isset($this->fields['itemdata'])) {
            $itemdata = json_decode($this->fields['itemdata'], true);
            if (count($itemdata) > 0) {
                // error_log('Test: '. print_r($this->fields['itemdata'], true));
                $i = 0;
                foreach ($this->isil as $isil) {
                    if (isset($itemdata[$isil])) {
                        foreach ($itemdata[$isil] as $val) {
                            $array[$i]['barcode'] = $val['bc'] ?? '';
                            $array[$i]['callnumber'] = $val['cn'] ?? '';
                            $i++;
                        }
                    } // end if
                } // end foreach
            } // end if
        } // end if
        return $array;
    }

    /**
     * Get local callnumbers of a special library. Refs #6324
     *
     * @return array
     * @deprecated (Cmp. refs #6324)
     */
    protected function getLocalCallnumbersByLibrary()
    {
        $array = [];
        $callnumbers = [];

        if (isset($this->fields['itemdata'])) {
            $itemdata = json_decode($this->fields['itemdata'], true);
            if (count($itemdata) > 0) {
                $i = 0;
                foreach ($this->isil as $isil) {
                    if (isset($itemdata[$isil])) {
                        foreach ($itemdata[$isil] as $val) {
                            // exclude equal callnumbers
                            if (false == in_array($val['cn'], $callnumbers)) {
                                $array[$i]['callnumber'] = $val['cn'];
                                $array[$i]['location'] = $isil;
                                $callnumbers[] = $val['cn'];
                                $i++;
                            }
                        } // end foreach
                    } // end if
                } // end foreach
            } // end if
        } // end if
        unset($callnumbers);
        return $array;
    }

    /**
     * Get the special local call number; for the moment only used by the
     * university library of Freiberg at finc marc 972i.
     *
     * @return string
     */
    protected function getLocalGivenCallnumber()
    {
        if (null != $this->getLocalMarcFieldOfLibrary()) {
            return $this->getFieldArray(
                $this->getLocalMarcFieldOfLibrary(),
                ['i']
            );
        }
        return [];
    }

    /**
     * Get an array of supplements and special issue entry.
     *
     * @return array
     * @link   http://www.loc.gov/marc/bibliographic/bd770.html
     */
    public function getSupplements()
    {
        //return $this->_getFieldArray('770', array('i','t')); // has been originally 'd','h','n','x' but only 'i' and 't' for ubl requested;
        $array = [];
        $supplement = $this->getMarcRecord()->getFields('770');
        // if not return void value
        if (!$supplement) {
            return $array;
        } // end if

        foreach ($supplement as $key => $line) {
            $array[$key]['pretext'] = ($line->getSubfield('i'))
                ? $line->getSubfield('i')->getData() : '';
            $array[$key]['text'] = ($line->getSubfield('t'))
                ? $line->getSubfield('t')->getData() : '';
            // get ppns of bsz
            $linkFields = $line->getSubfields('w');
            foreach ($linkFields as $current) {
                $text = $current->getData();
                // Extract parenthetical prefixes:
                if (preg_match(self::BSZ_PATTERN, $text, $matches)) {
                    //$id = $this->checkIfRecordExists($matches[2]);
                    //if ($id != null) {
                    $array[$key]['record_id'] = $matches[2] . $matches[3];
                    if (null != ($sid = $this->getSourceID())) {
                        $array[$key]['source_id'] = $sid;
                    }
                    //}
                    //break;
                }
            } // end foreach
        } // end foreach

        return $this->addFincIDToRecord($array);
    }

    /**
     * Special method to extracting the index of German prints of the marc21
     * field 024 indicator 8 subfield $a. Refs #1442
     *
     * @return array
     */
    public function getIndexOfGermanPrints()
    {
        // define a false indicator
        $lookfor_indicator = '8';
        $retval = [];

        $fields = $this->getMarcRecord()->getFields('024');
        if (!$fields) {
            return null;
        }
        foreach ($fields as $field) {
            // ->getIndicator(position)
            $subjectrow = $field->getIndicator('1');
            if ($subjectrow == $lookfor_indicator) {
                if ($subfield = $field->getSubfield('a')) {
                    if (preg_match('/^VD/i', $subfield->getData()) > 0) {
                        $retval[] = $subfield->getData();
                    }
                }
            }
        }
        return  $retval;
    }

    /**
     * Get an array of instrumentation notes taken from the local data
     * of the Petrucci music library subfield 590b
     *
     * @return array
     */
    public function getInstrumentation()
    {
        return $this->getFieldArray('590', ['b']);
    }

    /**
     * Get the ISSN from a record. Refs #969
     *
     * @return array
     */
    public function getISSNs() : array
    {
        return $this->getFieldArray('022', ['a']);
    }

    /**
     * Get the ISSN from a the parallel title of a record. Refs #969
     *
     * @return array
     */
    public function getISSNsParallelTitles()
    {
        return $this->getFieldArray('029', ['a']);
    }

    /**
     * Get the content-designated representation, in a different script, (field 880)
     * of the given field. fieldIterator is used if no Linkage in subfield 6 is
     * found.
     *
     * @param $field
     * @param int|bool $fieldIterator
     * @return array|bool
     */
    protected function getLinkedField($field, $fieldIterator = false)
    {
        // we need to know which field we are dealing with
        $tagNo = $field->getTag();

        // if we found a subfield 6 in given field we can compute the content of
        // subfield 6 in the corresponding field 880
        if ($sub6 = $field->getSubfield(6)) {
            $sub6Id = $tagNo . substr($sub6->getData(), 3);

            // now cycle through all available fields 880 and return the field with
            // the exact match on computed $sub6Id
            if ($linkedFields = $this->getMarcRecord()->getFields('880')) {
                foreach ($linkedFields as $current) {
                    if ($sub6Id == $current->getSubfield(6)->getData()) {
                        return $current;
                    }
                }
            }
        }

        // alternative approach, cycle through all available fields 880 and return
        // the field with a field and iterator match.
        if ($fieldIterator !== false) {
            if ($linkedFields = $this->getMarcRecord()->getFields('880')) {
                $i = 0;
                foreach ($linkedFields as $current) {
                    if ($tagNo == substr($current->getSubfield(6)->getData(), 0, 3)
                    ) {
                        if ($fieldIterator == $i) {
                            return $current;
                        }
                        $i++;
                    }
                }
            }
        }

        // not enough information to return linked field
        return false;
    }

    /**
     * Return an array of all values extracted from the linked field (MARC 880)
     * corresponding with the specified field/subfield combination.  If multiple
     * subfields are specified and $concat is true, they will be concatenated
     * together in the order listed -- each entry in the array will correspond with a
     * single MARC field.  If $concat is false, the return array will contain
     * separate entries for separate subfields.
     *
     * @param string $field     The MARC field number used for identifying the linked
     *                          MARC field to read
     * @param array  $subfields The MARC subfield codes to read
     * @param bool   $concat    Should we concatenate subfields?
     * @param string $separator Separator string (used only when $concat === true)
     *
     * @return array
     */
    protected function getLinkedFieldArray(
        $field,
        $subfields = null,
        $concat = true,
        $separator = ' '
    ) {
        // Default to subfield a if nothing is specified.
        if (!is_array($subfields)) {
            $subfields = ['a'];
        }

        // Initialize return array
        $matches = [];

        // Try to look up the specified field, return empty array if it doesn't
        // exist.
        $fields = $this->getMarcRecord()->getFields($field);
        if (!is_array($fields)) {
            return $matches;
        }

        $i = 0;
        // Extract all the linked fields.
        foreach ($fields as $currentField) {
            // Pass the iterator $i as a fallback if subfield $6 of MARC880 does not
            // contain the Linkage
            if ($linkedField = $this->getLinkedField($currentField, $i)) {
                // Extract all the requested subfields, if applicable.
                $next = $this
                    ->getSubfieldArray($linkedField, $subfields, $concat, $separator);
                $matches = array_merge($matches, $next);
            }
            $i ++;
        }

        return $matches;
    }

    /**
     * Get the original edition of the record.
     *
     * @return string
     */
    public function getEditionOrig()
    {
        $array = $this->getLinkedFieldArray('250', ['a']);
        return count($array) ? array_pop($array) : '';
    }

    /**
     * Get an array of publication detail lines with original notations combining
     * information from MARC field 260 and linked content in 880.
     *
     * @return array
     */
    public function getPublicationDetails()
    {
        $retval = [];

        $marcFields = ['260', '264'];

        // loop through all defined marcFields
        foreach ($marcFields as $marcField) {
            // now select all fields for the current marcField
            if ($fields = $this->getMarcRecord()->getFields($marcField)) {
                // loop through all fields of the current marcField
                foreach ($fields as $i => $current) {
                    // Marc 264abc should only be displayed if Ind.2==1
                    // Display any other Marc field if defined above
                    if ($marcField != '264'
                        || ($marcField == '264' && $current->getIndicator(2) == 1)
                    ) {
                        $place = $current->getSubfield('a')
                            ? $current->getSubfield('a')->getData() : null;
                        $name = $current->getSubfield('b')
                            ? $current->getSubfield('b')->getData() : null;
                        $date = $current->getSubfield('c')
                            ? $current->getSubfield('c')->getData() : null;

                        // Build objects to represent each set of data; these will
                        // transform seamlessly into strings in the view layer.
                        $retval[] = new \Bsz\RecordDriver\Response\PublicationDetails(
                            $place, $name, $date
                        );

                        // Build the publication details with additional graphical notations
                        // for the current set of publication details
                        if ($linkedField = $this->getLinkedField($current, $i)) {
                            $retval[] = new \Bsz\RecordDriver\Response\PublicationDetails(
                                $linkedField->getSubfield('a')
                                    ? $linkedField->getSubfield('a')->getData() : null,
                                $linkedField->getSubfield('b')
                                    ? $linkedField->getSubfield('b')->getData() : null,
                                $linkedField->getSubfield('c')
                                    ? $linkedField->getSubfield('c')->getData() : null
                            );
                        }
                    }
                }
            }
        }
        return $retval;
    }

    /**
     * Get an array of title detail lines with original notations combining
     * information from MARC field 245 and linked content in 880.
     *
     * @return array
     */
    public function getTitleDetails()
    {
        $title = '';
        if ($field = $this->getMarcRecord()->getField('245')) {
            if ($subfield = $field->getSubfield('a')) {
                // modified due to #13670
                // > Titel: 245$a $n $p $h $b $c
                $title = $subfield->getData();
                $title = $this->cleanString($title);
                foreach (['n', 'p', 'h', 'b', 'c'] as $subkey) {
                    if ($subfield = $field->getSubfield($subkey)) {
                        $title .= ' ' . $subfield->getData();
                    }
                }
            }
        }
        if ($field = $this->getMarcRecord()->getField('249')) {
            // 249$a and 249$v are repeatable
            if ($subfields = $field->getSubfields('a')) {
                $vs = $field->getSubfields('v');
                foreach ($subfields as $i=>$a) {
                    $title .= '. ' . $a->getData();
                    if (isset($vs[$i])) {
                        $title .= ' / ' . $vs[$i]->getData();
                    }
                }
            }
            // 249$b is non repeatable and applies to all $a$v combinations
            if ($field->getSubfield('b')) {
                $title .= ' : ' . $field->getSubfield('b')->getData();
            }
            // 249$c is non repeatable and applies to all $a$v combinations
            if ($field->getSubfield('c')) {
                $title .= ' / ' . $field->getSubfield('c')->getData();
            }
        }

        return array_merge(
            [$title],
            $this->getLinkedFieldArray('245', ['a', 'b', 'c'])
        );
    }


    /**
     * @return string
     */
    public function getTitle() : string
    {
        $tmp = [
            $this->getTitleShort(),
            ' : ',
            $this->getSubtitle()
        ];
        $title = implode('', $tmp);
        return $this->cleanString($title);
    }

    /**
     * Title from 245a
     * @return string
     */
    public function getTitleShort() : string
    {
        $field = $this->getMarcRecord()->getField('245');
        $subfield = $field->getSubfield('a');
        $title = $subfield ? $subfield->getData() : '';
        return $this->cleanString($title);
    }

    /**
     * Subtitle from 245b
     * @return string
     */
    public function getSubtitle() : string
    {
        $field = $this->getMarcRecord()->getField('245');
        $subfield = $field->getSubfield('b');
        $title = $subfield ? $subfield->getData() : '';
        return $this->cleanString($title);
    }

    /**
     * Get an array of work part title detail lines with original notations
     * from MARC field 505. The lines are combined of information from
     * subfield t and (if present) subfield r.
     *
     * @return array
     */
    public function getWorkPartTitleDetails()
    {
        $workPartTitles = [];
        //$titleRegexPattern = '/(\s[\/\.:]\s*)*$/';

        $truncateTrail = function ($string) /*use ($titleRegexPattern)*/ {
            // strip off whitespaces and some special characters
            // from the end of the input string
            #return preg_replace(
            #    $titleRegexPattern, '', trim($string)
            #);
            return rtrim($string, " \t\n\r\0\x0B" . '.:-/');
        };

        if ($fields = $this->getMarcRecord()->getFields('505')) {
            foreach ($fields as $field) {
                if ($subfields = $field->getSubfields('t')) {
                    $rs = $field->getSubfields('r');
                    foreach ($subfields as $i=>$subfield) {
                        // each occurance of $t gets $a pretached if it exists
                        if (isset($rs[$i])) {
                            $workPartTitles[] =
                                $truncateTrail($subfield->getData()) . ': ' .
                                $truncateTrail($rs[$i]->getData());
                        } else {
                            $workPartTitles[] =
                                $truncateTrail($subfield->getData());
                        }
                    }
                }
            }
        }

        return $workPartTitles;
    }

    /**
     * Get an array of work title detail lines with original notations
     * from MARC field 700.
     *
     * @return array
     */
    public function getWorkTitleDetails()
    {
        $workTitles = [];

        $truncateTrail = function ($string) {
            return rtrim($string, " \t\n\r\0\x0B" . '.:-/');
        };

        if ($fields = $this->getMarcRecord()->getFields('700')) {
            foreach ($fields as $field) {
                if ($field->getSubfield('t') && $field->getSubfield('a')) {
                    $workTitles[] =
                        $truncateTrail($field->getSubfield('a')->getData()) . ': ' .
                        $truncateTrail($field->getSubfield('t')->getData());
                }
            }
        }

        return $workTitles;
    }

    /**
     * Get the original statement of responsibility that goes with the title (i.e.
     * "by John Smith").
     *
     * @return string
     */
    public function getTitleStatementOrig()
    {
        $array = $this->getLinkedFieldArray('245', ['c']);
        return array_pop($array);
    }

    /**
     * Support method for getSeries() -- given a field specification, look for
     * series information in the MARC record.
     *
     * @param array $fieldInfo Associative array of field => subfield information
     * (used to find series name)
     *
     * @return array
     */
    protected function getSeriesFromMARC($fieldInfo)
    {
        $matches = [];

        $buildSeries = function ($field, $subfields) use (&$matches) {
            // Can we find a name using the specified subfield list?
            $name = $this->getSubfieldArray($field, $subfields);
            if (isset($name[0])) {
                $currentArray = ['name' => $name[0]];

                // Can we find a number in subfield v?  (Note that number is
                // always in subfield v regardless of whether we are dealing
                // with 440, 490, 800 or 830 -- hence the hard-coded array
                // rather than another parameter in $fieldInfo).
                $number
                    = $this->getSubfieldArray($field, ['v']);
                if (isset($number[0])) {
                    $currentArray['number'] = $number[0];
                }

                // Save the current match:
                $matches[] = $currentArray;
            }
        };

        // Loop through the field specification....
        foreach ($fieldInfo as $field => $subfields) {
            // Did we find any matching fields?
            $series = $this->getMarcRecord()->getFields($field);
            if (is_array($series)) {
                // use the fieldIterator as fallback for linked data in field 880 that
                // is not linked via $6
                $fieldIterator = 0;
                foreach ($series as $currentField) {
                    // Can we find a name using the specified subfield list?
                    if (isset($this->getSubfieldArray($currentField, $subfields)[0])) {
                        $buildSeries($currentField, $subfields);

                        // attempt to find linked data in 880 field
                        if ($linkedData = $this->getLinkedField($currentField, $fieldIterator)) {
                            $buildSeries($linkedData, $subfields);
                        }
                    }
                    $fieldIterator ++;
                }
            }
        }

        return $matches;
    }

    /**
     * Get an array of information about Journal holdings realised for the
     * special needs of University library of Chemnitz. MAB fields 720.
     * Refs #328
     *
     * @return array
     */
    public function getJournalHoldings()
    {
        $retval = [];
        $match = [];

        $fields = $this->getMarcRecord()->getFields('971');
        if (!$fields) {
            return [];
        }

        $key = 0;
        foreach ($fields as $field) {
            if ($subfield = $field->getSubfield('k')) {
                preg_match(
                    '/(.*)##(.*)##(.*)/',
                    trim($subfield->getData()),
                    $match
                );
                $retval[$key]['callnumber'] = trim($match[1]);
                $retval[$key]['holdings'] = trim($match[2]);
                $retval[$key]['footnote'] = trim($match[3]);
                $retval[$key]['is_holdable'] = 1;

                if (count($this->getBarcode()) == 1) {
                    $current = $this->getBarcode();
                    $barcode = $current[0];
                } else {
                    $barcode = '';
                }
                $retval[$key]['link'] =
                    '/Record/' . $this->getUniqueID()
                    . '/HoldJournalCHE?callnumber='
                    . urlencode($retval[$key]['callnumber'])
                    . '&barcode=' . $barcode;
                $key++;
            }
        }
        return $retval;
    }

    /**
     * Return a local access number for call number.
     * Marc field depends on library e.g. 975 for WHZ.
     * Seems to be very extraordinary special case. Refs #1302
     *
     * @return array
     */
    protected function getLocalAccessNumber()
    {
        if (null != $this->getLocalMarcFieldOfLibrary()) {
            return $this->getFieldArray(
                $this->getLocalMarcFieldOfLibrary(),
                ['o']
            );
        }
        return [];
    }

    /**
     * Return a local access number for call number.
     * Marc field depends on library e.g. 986 for GfzK.
     * Seems to be very extraordinary special case. Refs #7924
     *
     * @return array
     */
    public function getLocalSubject()
    {
        $retval = [];

        $fields = $this->getMarcRecord()->getFields(
            $this->getLocalMarcFieldOfLibrary()
        );
        if (!$fields) {
            return null;
        }
        foreach ($fields as $key => $field) {
            if ($q = $field->getSubfield('q')) {
                $retval[$key] = $q->getData();
            }
        }
        return $retval;
    }

    /**
     * Get all local class subjects. First realization for HGB. Refs #2626
     *
     * @return array
     */
    protected function getLocalClassSubjects()
    {
        $array = [];
        $classsubjects = $this->getMarcRecord()->getFields('979');
        // if not return void value
        if (!$classsubjects) {
            return $array;
        } // end if
        foreach ($classsubjects as $key => $line) {
            // if subfield with class subjects exists
            if ($line->getSubfield('f')) {
                // get class subjects
                $array[$key]['nb'] = $line->getSubfield('f')->getData();
            } // end if subfield a
            if ($line->getSubfield('9')) {
                $array[$key]['data'] = $line->getSubfield('9')->getData();
            }
        } // end foreach
        return $array;
    }

    /**
     * Returning local format field of a library using an consortial defined
     * field with subfield $c. Marc field depends on library e.g. 970 for HMT or
     * 972 for TUBAF
     *
     * @return array
     */
    public function getLocalFormat()
    {
        if (null != $this->getLocalMarcFieldOfLibrary()) {
            if (count($localformat = $this->getFieldArray($this->getLocalMarcFieldOfLibrary(), ['c'])) > 0) {
                foreach ($localformat as &$line) {
                    if ($line != "") {
                        $line = trim('local_format_' . strtolower($line));
                    }
                }
                unset($line);
                return $localformat;
            }
        }
        return [];
    }

    /**
     * Returns lazily the library specific Marc field configured by CustomIndex
     * settings in config.ini. Refs 7063
     *
     * @return mixed
     */
    protected function getLocalMarcFieldOfLibrary()
    {
        // return the library specific Marc field if its already set
        if ($this->localMarcFieldOfLibrary != null) {
            return $this->localMarcFieldOfLibrary;
        }

        // get the library specific Marc field configured by CustomIndex settings in
        // config.ini
        if (isset($this->mainConfig->CustomIndex->localMarcFieldOfLibraryNamespace)) {
            $namespace = $this->mainConfig->CustomIndex->localMarcFieldOfLibraryNamespace;
            if (isset($this->mainConfig->CustomIndex->localMarcFieldOfLibraryMapping)) {
                foreach ($this->mainConfig->CustomIndex->localMarcFieldOfLibraryMapping as $mappingValue) {
                    list($ns, $fn) = explode(':', $mappingValue);
                    if (trim($ns) == trim($namespace)) {
                        $this->localMarcFieldOfLibrary = $fn;
                        break;
                    }
                }
            }
        } else {
            $this->debug('Namespace setting for localMarcField is missing.');
        }
        return $this->localMarcFieldOfLibrary;
    }

    /**
     * Return a local notice via an consortial defined field with subfield $k.
     * Marc field depends on library e.g. 970 for HMT or 972 for TUBAF.
     * Refs #1308
     *
     * @return array
     */
    protected function getLocalNotice()
    {
        if (null != $this->getLocalMarcFieldOfLibrary()) {
            return $this->getFieldArray($this->getLocalMarcFieldOfLibrary(), ['k']);
        }
        return [];
    }

    /**
     * Return a local signature via an consortial defined field with subfield $f.
     * Marc field depends on library e.g. 986 for GFZK. Refs #8146
     *
     * @return array
     */
    public function getLocalSignature()
    {
        $retval = [];

        $fields = $this->getMarcRecord()
            ->getFields($this->getLocalMarcFieldOfLibrary());
        if (!$fields) {
            return null;
        }
        foreach ($fields as $key => $field) {
            if ($q = $field->getSubfield('f')) {
                $retval[$key][] = $q->getData();
            }
        }
        return $retval;
    }

    /**
     * Return a stock specification via a consortial defined field of
     * subfield $h. Marc field depends on library e.g. 972 for TUF.
     *
     * @return array
     */
    protected function getLocalStockSpecification()
    {
        if (null != $this->getLocalMarcFieldOfLibrary()) {
            return $this->getFieldArray(
                $this->getLocalMarcFieldOfLibrary(),
                ['h']
            );
        }
        return [];
    }

    /**
     * Get an array of musical heading based on a swb field
     * at the marc field.
     *
     * @return mixed    null if there's no field or array with results
     */
    public function getMusicHeading()
    {
        $retval = [];

        $fields = $this->getMarcRecord()->getFields('937');
        if (!$fields) {
            return null;
        }
        foreach ($fields as $key => $field) {
            if ($d = $field->getSubfield('d')) {
                $retval[$key][] = $d->getData();
            }
            if ($e = $field->getSubfield('e')) {
                $retval[$key][] = $e->getData();
            }
            if ($f = $field->getSubfield('f')) {
                $retval[$key][] = $f->getData();
            }
        }
        return $retval;
    }

    /**
     * Get an array of succeeding titles for the record. Opposite method to
     * getting previous title of marc field 780.
     *
     * @return array
     */
    public function getNewerTitles()
    {
        $array = [];
        $previous = $this->getMarcRecord()->getFields('785');

        // if no entry return void
        if (!$previous) {
            return $array;
        }

        foreach ($previous as $key => $line) {
            $array[$key]['pretext'] = ($line->getSubfield('i'))
                ? $line->getSubfield('i')->getData() : '';
            $array[$key]['text'] = ($line->getSubfield('a'))
                ? $line->getSubfield('a')->getData() : '';
            if (empty($array[$key]['text'])) {
                $array[$key]['text'] = ($line->getSubfield('t'))
                    ? $line->getSubfield('t')->getData() : '';
            }
            // get ppns of bsz
            $linkFields = $line->getSubfields('w');
            foreach ($linkFields as $current) {
                $text = $current->getData();
                // Extract parenthetical prefixes:
                if (preg_match(self::BSZ_PATTERN, $text, $matches)) {
                    $array[$key]['record_id'] = $matches[2] . $matches[3];
                    if (null != ($sid = $this->getSourceID())) {
                        $array[$key]['source_id'] = $sid;
                    }
                }
            } // end foreach
        } // end foreach

        return $this->addFincIDToRecord($array);
    }

    /**
     * Get notice of a title representing a special case of University
     * library of Chemnitz: MAB field 999l
     *
     * @return string
     */
    protected function getNotice()
    {
        return $this->getFirstFieldValue('971', ['l']);
    }

    /**
     * Returns the contens of MARC 787 as an array using 787$i as associative
     * key and having the array value with the key 'text' containing the
     * contents of 787 $a{t} and the key 'link' containing a PPN to the
     * mentioned record in 787 $a{t}.
     *
     * @return array|null
     */
    public function getOtherRelationshipEntry()
    {
        $retval = [];
        $defaultHeading = 'Note';
        // container for collecting recordIDs to the result array #12941
        $tempIds = [];

        $fields = $this->getMarcRecord()->getFields('787');
        if (!$fields) {
            return null;
        }
        foreach ($fields as $field) {
            // don't do anything unless we have something in $a
            if ($a = $field->getSubfield('a')) {
                // do we have a main entry heading?
                if ($i = $field->getSubfield('i')) {
                    // build the text to be displayed from subfields $a and/or $t
                    $text = ($t = $field->getSubfield('t'))
                        ? $a->getData() . ': ' . $t->getData()
                        : $a->getData();

                    $linkFields = $field->getSubfields('w');
                    foreach ($linkFields as $current) {
                        $ids = $current->getData();

                        // Extract parenthetical prefixes:
                        if (preg_match(self::BSZ_PATTERN, $ids, $matches)) {
                            // use the same key to set the record_id into the
                            // $retval array like it is used for the other
                            // content below
                            $tempIds[$i->getData()]['record_id']
                                = $matches[2] . $matches[3];
                        }
                    } // end foreach

                    // add ids already here to the temporary array
                    // instead of the end of the function with the return value
                    $tempIds = $this->addFincIDToRecord($tempIds);

                    // does a linked record exist
                    $link = ($w = $field->getSubfield('w'))
                        ? $w->getData() : '';

                    // we expect the links to be ppns prefixed with an ISIL so
                    // strip the ISIL
                    $ppn = preg_replace(
                        "/^\(([A-z])+\-([A-z0-9])+\)\s?/", "", $link
                    );

                    $record_id = null;
                    if (!empty($tempIds[$i->getData()]['id'])) {
                        $record_id = $tempIds[$i->getData()]['record_id'];
                    }

                    $id = null;
                    if (!empty($tempIds[$i->getData()]['id'])) {
                        $id = $tempIds[$i->getData()]['id'];
                    }

                    // let's use the main entry heading as associative key and
                    // push the gathered content into the retval array
                    // add recordIDs 'record_id' and 'id' to the result array
                    // cmp. #12941
                    $retval[$i->getData()][] = [
                        'text' => $text,
                        'link' => (!empty($ppn) ? $ppn : $link),
                        'record_id' => $record_id,
                        'id' => $id
                    ];
                } else {
                    // no main entry heading found, so push subfield a's content
                    // into retval using the defaultHeading
                    $retval[$defaultHeading][] = [
                        'text' => $a->getData(),
                        'link' => ''
                    ];
                }
            }
        }

        return $retval;
    }

    /**
     * Get an array of style/genre of a piece taken from the local data
     * of the Petrucci music library subfield 590a
     *
     * @return array
     */
    public function getPieceStyle()
    {
        return $this->getFieldArray('590', ['a']);
    }

    /**
     * Get specific marc information about parallel editions. Unflexible
     * solution for HMT only implemented.
     *
     * @todo More flexible implementation
     *
     * @return array
     */
    protected function getParallelEditions()
    {
        $array = [];
        $fields = ['775'];
        $i = 0;

        foreach ($fields as $field) {
            $related = $this->getMarcRecord()->getFields($field);
            // if no entry break it
            if ($related) {
                foreach ($related as $key => $line) {
                    // check if subfields i or t exist. if yes do a record.
                    if ($line->getSubfield('i') || $line->getSubfield('t')) {
                        $array[$i]['identifier'] = ($line->getSubfield('i'))
                            ? $line->getSubfield('i')->getData() : '';
                        $array[$i]['text'] = ($line->getSubfield('t'))
                            ? $line->getSubfield('t')->getData() : '';
                        // get ppns of bsz
                        $linkFields = $line->getSubfields('w');
                        if (is_array($linkFields) && count($linkFields) > 0) {
                            foreach ($linkFields as $current) {
                                $text = $current->getData();
                                // Extract parenthetical prefixes:
                                if (preg_match(self::BSZ_PATTERN, $text, $matches)) {
                                    $array[$key]['record_id'] = $matches[2] . $matches[3];
                                    if (null != ($sid = $this->getSourceID())) {
                                        $array[$key]['source_id'] = $sid;
                                    }
                                }
                            } // end foreach
                        } // end if
                        $i++;
                    } // end if
                } // end foreach
            }
        }
        return $this->addFincIDToRecord($array);
    }

    /**
     * Get an array of previous titles for the record.
     *
     * @return array
     */
    public function getPreviousTitles()
    {
        $array = [];
        $previous = $this->getMarcRecord()->getFields('780');

        // if no entry return void
        if (!$previous) {
            return $array;
        }

        foreach ($previous as $key => $line) {
            $array[$key]['pretext'] = ($line->getSubfield('i'))
                ? $line->getSubfield('i')->getData() : '';
            $array[$key]['text'] = ($line->getSubfield('a'))
                ? $line->getSubfield('a')->getData() : '';
            if (empty($array[$key]['text'])) {
                $array[$key]['text'] = ($line->getSubfield('t'))
                    ? $line->getSubfield('t')->getData() : '';
            }
            // get ppns of bsz
            $linkFields = $line->getSubfields('w');
            foreach ($linkFields as $current) {
                $text = $current->getData();
                // Extract parenthetical prefixes:
                if (preg_match(self::BSZ_PATTERN, $text, $matches)) {
                    $array[$key]['record_id'] = $matches[2] . $matches[3];
                    if (null != ($sid = $this->getSourceID())) {
                        $array[$key]['source_id'] = $sid;
                    }
                }
            } // end foreach
        } // end foreach

        return $this->addFincIDToRecord($array);
    }

    /**
     * Get an array of previous titles for the record.
     *
     * @todo use HttpService for URL query
     * @todo change currency service
     * @todo pass prices by euro currency
     *
     * @return string
     */
    public function getPrice()
    {
        $currency = $this->getFirstFieldValue('365', ['c']);
        $price = $this->getFirstFieldValue('365', ['b']);
        if (!empty($currency) && !empty($price)) {
            // if possible convert it in euro
            if (is_array($converted =
                json_decode(str_replace(
                    ['lhs','rhs','error','icc'],
                    ['"lhs"','"rhs"','"error"','"icc"'],
                    file_get_contents("http://www.google.com/ig/calculator?q=" . $price . $currency . "=?EUR")
                ), true)
            )) {
                if (empty($converted['error'])) {
                    $rhs = explode(' ', trim($converted['rhs']));
                    return  money_format('%.2n', $rhs[0]);
                }
            }
            return $currency . " " . $price;
        }
        return "";
    }

    /**
     * Get the provenience of a title.
     *
     * @return array
     */
    public function getProvenience()
    {
        return $this->getFieldArray('561', ['a']);
    }

    /**
     * Checked if an title is ordered by the library using an consortial defined
     * field with subfield $m. Marc field depends on library e.g. 970 for HMT or
     * 972 for TUBAF
     *
     * @return bool
     */
    protected function getPurchaseInformation()
    {
        if (null != $this->getLocalMarcFieldOfLibrary()) {
            if ($this->getFirstFieldValue($this->getLocalMarcFieldOfLibrary(), ['m']) == 'e') {
                return true;
            }
        }
        return false;
    }

    /**
     * Get a short list of series for ISBD citation style
     *
     * @return array
     * @link   http://www.loc.gov/marc/bibliographic/bd830.html
     */
    protected function getSeriesWithVolume()
    {
        return $this->getFieldArray('830', ['a', 'v'], false);
    }

    /**
     * Get source id of marc record. Alternate method getFirstFieldValue returns
     * null by value "0" therefor it doesn't fit properly.
     *
     * @return string|null
     */
    /* removed erroneous inheritance, this function is present and working in SolrDefaultFincTrait, DM
        public function getSourceID()
        {
            $source_ids = $this->getMarcRecord()->getFields('980');
            if (!$source_ids) {
                return null;
            }
            return (string)$source_ids[0]->getSubfield('b')->getData();
        }
    */

    /**
     * Get local classification of UDK.
     *
     * @return array
     * @deprecated Seems to be only for HTWK in use formerly?
     */
    protected function getUDKs()
    {
        $array = [];
        if (null != $this->getLocalMarcFieldOfLibrary()) {
            $udk = $this->getMarcRecord()->getFields(
                $this->getLocalMarcFieldOfLibrary()
            );
            // if not return void value
            if (!$udk) {
                return $array;
            } // end if

            foreach ($udk as $key => $line) {
                // if subfield with udk exists
                if ($line->getSubfield('f')) {
                    // get udk
                    $array[$key]['index'] = $line->getSubfield('f')->getData();
                    // get udk notation
                    // fixes by update of File_MARC to version 0.8.0
                    // @link https://intern.finc.info/issues/2068
                    /*
                    if ($notation = $line->getSubfield('n')) {
                        // get first value
                        $array[$key]['notation'][] = $notation->getData();
                        // iteration over udk notation
                        while ($record = $notation->next()) {
                            $array[$key]['notation'][] = $record->getData();
                            $notation = $record;
                        }
                    } // end if subfield n
                    unset($notation);
                    */
                    if ($record = $line->getSubfields('n')) {
                        // iteration over rvk notation
                        foreach ($record as $field) {
                            $array[$key]['notation'][] = $field->getData();
                        }
                    } // end if subfield n
                } // end if subfield f
            } // end foreach
        }
        //error_log(print_r($array, true));
        return $array;
    }

    /**
     * Get addional entries for personal names.
     *
     * @return array
     * @link   http://www.loc.gov/marc/bibliographic/bd700.html
     */
    protected function getAdditionalAuthors()
    {
        // result array to return
        $retval = [];

        $results = $this->getMarcRecord()->getFields('700');
        if (!$results) {
            return $retval;
        }

        foreach ($results as $key => $line) {
            $retval[$key]['name'] = ($line->getSubfield('a'))
                ? $line->getSubfield('a')->getData() : '';
            $retval[$key]['dates'] = ($line->getSubfield('d'))
                ? $line->getSubfield('d')->getData() : '';
            $retval[$key]['relator'] = ($line->getSubfield('e'))
                ? $line->getSubfield('e')->getData() : '';
        }
        // echo "<pre>"; print_r($retval); echo "</pre>";
        return $retval;
    }

    /**
     * Get specific marc information about additional items. Unflexible solution
     * for UBL only implemented. Refs. #1315
     *
     * @return array
     */
    public function getAdditionals()
    {
        $array = [];
        $fields = ['770','775','776'];
        $subfields = ['a', 'l', 't', 'b', 'd', 'e', 'f', 'h', 'o', '7','z'];
        $i = 0;

        foreach ($fields as $field) {
            $related = $this->getMarcRecord()->getFields($field);
            // if no entry stop
            if ($related) {
                // loop through all found fields
                foreach ($related as $key => $line) {
                    // first lets look for identifiers - identifiers are vital as
                    // those are used to identify the text in the frontend (e.g. as
                    // table headers)
                    // so, proceed only if we have an identifier
                    if ($line->getSubfield('i')) {
                        // lets collect the text
                        // https://intern.finc.info/issues/6896#note-7
                        $text = [];
                        foreach ($subfields as $subfield) {
                            if ($line->getSubfield($subfield)) {
                                $text[] = $line->getSubfield($subfield)->getData();
                            }
                        }

                        // we can have text without links but no links without text, so
                        // only proceed if we actually have a value for the text
                        if (count($text) > 0) {
                            $array[$i] = [
                                'text'       => implode(', ', $text),
                                'identifier' => ($line->getSubfield('i'))
                                    ? $line->getSubfield('i')->getData() : ''
                            ];

                            // finally we can try to use given PPNs (from the BSZ) to
                            // link the record
                            if ($linkFields = $line->getSubfields('w')) {
                                foreach ($linkFields as $current) {
                                    $text = $current->getData();
                                    // Extract parenthetical prefixes:
                                    if (preg_match(self::BSZ_PATTERN, $text, $matches)) {
                                        $array[$i]['record_id']
                                            = $matches[2] . $matches[3];
                                        if (null != ($sid = $this->getSourceID())) {
                                            $array[$i]['source_id'] = $sid;
                                        }
                                    }
                                }
                            }

                            // at least we found some identifier and text so increment
                            $i++;
                        }
                    }
                }
            }
        }
        return $this->addFincIDToRecord($array);
    }

    /**
     * Get specific marc information about containing items. Unflexible solution
     * for UBL only implemented.
     *
     * @return array
     * @link   https://intern.finc.info/fincproject/issues/1315
     */
    public function getSetMultiPart()
    {
        $array = [];
        $fields = [
            '773' => ['a'=>['',''], 't'=>[': ',''], 'g'=>[' ; ','']],
            '490' => ['a'=>['','']],
            '800' => ['a'=>['',': '], 't'=>['',''], 'v'=>[' ; ',''],'g'=>[' ; ','']],
            '810' => ['a'=>['',': '], 't'=>['',''], 'v'=>[' ; ',''],'g'=>[' ; ','']],
            '811' => ['a'=>['',': '], 't'=>['',''], 'v'=>[' ; ',''],'g'=>[' ; ','']],
            '830' => ['a'=>['',''], 'v'=>[' ; ','']]
        ];
        $i = 0;

        foreach ($fields as $field => $subfields) {
            $related = $this->getMarcRecord()->getFields($field);
            // if no entry stop
            if ($related) {
                // loop through all found fields
                foreach ($related as $key => $line) {
                    // first lets look for identifiers - identifiers are vital as
                    // those are used to identify the text in the frontend (e.g. as
                    // table headers)
                    // so, proceed only if we have an identifier

                    // lets collect the text
                    // https://intern.finc.info/issues/6896#note-7
                    $text = [];
                    foreach ($subfields as $subfield => list($l_delim, $r_delim)) {
                        $val = $line->getSubfield($subfield);
                        if ($field == '773' && $subfield == 'a') {
                            if ($line->getIndicator(1) == 1) {
                                $field245 = $this->getMarcRecord()->getField('245');
                                if ($sub245a = $field245->getSubfield('a')) {
                                    $text[] = $sub245a->getData();
                                }
                                unset($subfields['t']);
                            } elseif (empty($val)) {
                                continue;
                            } else {
                                $text[] = $l_delim . $val->getData() . $r_delim;
                            }
                        } else {
                            if (empty($val)) {
                                continue;
                            }
                            if ($field == '490') {
                                if ($line->getIndicator(1) == 0) {
                                    $text[] = $l_delim . $val->getData() . $r_delim;
                                }
                            } elseif ($subfield == 'v' && in_array($field, ['800', '810', '811'])) {
                                if (!empty($val)) {
                                    $text[] = $l_delim . $val->getData() . $r_delim;
                                    // do not use the next (and last) subfield $g,
                                    // if $v is already set
                                    break;
                                }
                            } else {
                                $text[] = $l_delim . $val->getData() . $r_delim;
                            }
                        }
                    }

                    // we can have text without links but no links without text, so
                    // only proceed if we actually have a value for the text
                    if (count($text) > 0) {
                        $array[$i] = [
                            'text'       => implode('', $text),
                            'identifier' => ($line->getSubfield('i'))
                                ? $line->getSubfield('i')->getData() : 'Set Multipart'
                        ];

                        // finally we can try to use given PPNs (from the BSZ) to
                        // link the record
                        if ($linkFields = $line->getSubfields('w')) {
                            foreach ($linkFields as $current) {
                                $text = $current->getData();
                                // Extract parenthetical prefixes:
                                if (preg_match(self::BSZ_PATTERN, $text, $matches)) {
                                    $array[$i]['record_id']
                                        = $matches[2] . $matches[3];
                                    if (null != ($sid = $this->getSourceID())) {
                                        $array[$i]['source_id'] = $sid;
                                    }
                                }
                            }
                        }
                        // at least we found some identifier and text so increment
                        $i++;
                    }
                }
            }
        }
        return $this->addFincIDToRecord($array);
    }

    /**
     * Returns notes and additional information stored in Marc 546$a.
     * Refs. #8509
     *
     * @return array|null
     */
    public function getAdditionalNotes()
    {
        $retval = [];

        $fields = $this->getMarcRecord()->getFields('546');

        if (!$fields) {
            return null;
        }
        foreach ($fields as $field) {
            if ($subfield = $field->getSubfield('a')) {
                $retval[] = $subfield->getData();
            }
        }
        return $retval;
    }

    public function getAllNotes()
    {
        $notes = array_merge(
            (array)$this->getGeneralNotes(),
            (array)$this->getAdditionalNotes()
        );
        foreach ($notes as &$note) {
            if (preg_match('/(.*)\.\s*$/', $note, $matches)) {
                $note = $matches[1];
            }
        }
        return $notes;
    }

    /**
     * Marc specific implementation for retrieving hierarchy parent id(s).
     * Refs #8369
     *
     * @return array
     */
    public function getHierarchyParentID()
    {
        $parentID = [];
        // IMPORTANT! always keep fields in same order as in getHierarchyParentTitle
        $fieldList = [
            ['490'],
            ['773'],
            ['800', '810', '811'],
            ['830']
        ];

        $idRetrieval = function ($value) {
            // use preg_match to get rid of the isil
            preg_match("/^(\([A-z]*-[A-z0-9]*\))?\s*([A-z0-9]*)\s*$/", $value, $matches);
            if (!empty($matches[2])) {
                $query = 'record_id:' . $matches[2];
                if ($sid = $this->fields['source_id']) {
                    $query .= ' AND source_id:' . $sid;
                }
                $result = $this->searchService->search('Solr', new Query($query));
                if (count($result) === 0) {
                    $this->debug('Could not retrieve id for record with ' . $query);
                    return null;
                }
                return current($result->getRecords())->getUniqueId();
            }
            $this->debug('Pregmatch pattern in getHierarchyParentID failed for ' .
                $value
            );
            return $value;
        };

        // loop through all field lists in their particular order (as in
        // getHierchyParentTitle) and build the $parentID array
        foreach ($fieldList as $fieldNumbers) {
            foreach ($fieldNumbers as $fieldNumber) {
                $fields = $this->getMarcRecord()->getFields($fieldNumber);
                foreach ($fields as $field) {
                    if ($field->getSubfield('w')) {
                        $parentID[] = $idRetrieval(
                            $field->getSubfield('w')->getData()
                        );
                    } elseif ($fieldNumber == '490') {
                        // https://intern.finc.info/issues/8704
                        if ($field->getIndicator(1) == 0
                            && $subfield = $field->getSubfield('a')
                        ) {
                            $parentID[] = null;
                        }
                    }
                }
            }
        }

        // as a fallback return the parent ids stored in Solr
        if (count($parentID) == 0) {
            return parent::getHierarchyParentID();
        }

        return $parentID;
    }

    /**
     * Marc specific implementation for compiling hierarchy parent titles.
     * Refs #8369
     *
     * @return array
     */
    public function getHierarchyParentTitle()
    {
        $parentTitle = [];

        // https://intern.finc.info/issues/8725
        $vgSelect = function ($field) {
            if ($field->getSubfield('v')) {
                return $field->getSubfield('v')->getData();
            } elseif ($field->getSubfield('g')) {
                return $field->getSubfield('g')->getData();
            }
            return false;
        };

        // start with 490 (https://intern.finc.info/issues/8704)
        $fields = $this->getMarcRecord()->getFields('490');
        foreach ($fields as $field) {
            if ($field->getIndicator(1) == 0
                && $subfield = $field->getSubfield('a')
            ) {
                $parentTitle[] = $subfield->getData(); // {490a}
            }
        }

        // now check if 773 is available and LDR 7 != (a || s)
        $fields = $this->getMarcRecord()->getFields('773');
        if ($fields && !in_array($this->getMarcRecord()->getLeader()[7], ['a', 's'])) {
            foreach ($fields as $field) {
                if ($field245 = $this->getMarcRecord()->getField('245')) {
                    $parentTitle[] =
                        ($field245->getSubfield('a') ? $field245->getSubfield('a')->getData() : '') .
                        ($field->getSubfield('g') ? '; ' . $field->getSubfield('g')->getData() : '')
                    ; // {245a}{; 773g}
                }
            }
        } else {
            // build the titles differently if LDR 7 == (a || s)
            foreach ($fields as $field) {
                $parentTitle[] =
                    ($field->getSubfield('a') ? $field->getSubfield('a')->getData() : '') .
                    ($field->getSubfield('t') ? ': ' . $field->getSubfield('t')->getData() : '') .
                    ($field->getSubfield('g') ? ', ' . $field->getSubfield('g')->getData() : '')
                ; // {773a}{: 773t}{, g}
            }
        }

        // now proceed with 8xx fields
        $fieldList = ['800', '810', '811'];
        foreach ($fieldList as $fieldNumber) {
            $fields = $this->getMarcRecord()->getFields($fieldNumber);
            foreach ($fields as $field) {
                $parentTitle[] =
                    ($field->getSubfield('a') ? $field->getSubfield('a')->getData() : '') .
                    ($field->getSubfield('t') ? ': ' . $field->getSubfield('t')->getData() : '') .
                    ($vgSelect($field) ? ' ; ' . $vgSelect($field) : '')
                ; // {800a: }{800t}{ ; 800v}
            }
        }

        // handle field 830 differently
        $fields = $this->getMarcRecord()->getFields('830');
        foreach ($fields as $field) {
            $parentTitle[] =
                ($field->getSubfield('a') ? $field->getSubfield('a')->getData() : '') .
                ($vgSelect($field) ? ' ; ' . $vgSelect($field) : '')
            ; // {830a}{ ; 830v}
        }

        // as a fallback return the parent titles stored in Solr
        if (count($parentTitle) == 0) {
            return parent::getHierarchyParentTitle();
        }

        return $parentTitle;
    }

    /**
     * Special method to extracting the data of the marc21 field 689 of the
     * the bsz heading subjects chains.
     *
     * @return array
     */
    public function getAllSubjectHeadingsExtended()
    {
        // define a false indicator
        $firstindicator = 'x';
        $retval = [];

        $fields = $this->getMarcRecord()->getFields('689');
        foreach ($fields as $field) {
            $subjectrow = $field->getIndicator('1');
            if ($subjectrow != $firstindicator) {
                $key = (isset($key) ? $key + 1 : 0);
                $firstindicator = $subjectrow;
            }
            // #5668 #5046 BSZ MARC may contain uppercase subfields but solrmarc set to lowercase them which introduces single char topics
            if ($subfields = $field->getSubfields('a')) {
                foreach ($subfields as $subfield) {
                    if (strlen($subfield->getData()) > 1) {
                        $retval[$key]['subject'][] = $subfield->getData();
                    }
                }
            }
            if ($subfield = $field->getSubfield('t')) {
                $retval[$key]['subject'][] = $subfield->getData();
            }
            if ($subfield = $field->getSubfield('9')) {
                $retval[$key]['subsubject'] = $subfield->getData();
            }
        }
        return  $retval;
    }

    /**
     * Get all subject headings associated with this record.  Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific.
     *
     * @param boolean $extended If dynamic index extension activated
     *
     * @return array
     */
    public function getAllSubjectHeadings($extended = false)
    {
        // These are the fields that may contain subject headings:
        $fields = [
            '600', '610', '611', '630', '648', '650', '651', '653', '655', '656'
        ];

        // skip fields containing these terms in $2
        $skipTerms = isset($this->mainConfig->SubjectHeadings->remove) ?
            $this->mainConfig->SubjectHeadings->remove->toArray() : [];

        $skipThisField = function ($field) use ($skipTerms) {
            $subField = $field->getSubField('2');
            return !($subField && in_array($subField->getData(), $skipTerms));
        };

        // This is all the collected data:
        $retval = [];

        // Try each MARC field one at a time:
        foreach ($fields as $field) {
            // Do we have any results for the current field?  If not, try the next.
            $results = $this->getMarcRecord()->getFields($field);
            if (!$results) {
                continue;
            }

            // If we got here, we found results -- let's loop through them.
            foreach ($results as $result) {
                // Start an array for holding the chunks of the current heading:
                $current = [];

                // check if this field should be skipped
                if ($skipThisField($result)) {

                    // Get all the chunks and collect them together:
                    $subfields = $result->getSubfields();
                    if ($subfields) {
                        foreach ($subfields as $subfield) {
                            // Numeric subfields are for control purposes and should not
                            // be displayed:
                            if (!is_numeric($subfield->getCode())) {
                                $current[] = $subfield->getData();
                            }
                        }
                        // If we found at least one chunk, add a heading to our result:
                        if (!empty($current)) {
                            $retval[] = $current;
                        }
                    }
                }

                // If we found at least one chunk, add a heading to our result:
                if (!empty($current)) {
                    $retval[] = $current;
                }
            }
        }

        if (empty($retval)) {
            $retval = parent::getAllSubjectHeadings();
        }

        // Remove duplicates and then send back everything we collected:
        return array_map(
            'unserialize', array_unique(array_map('serialize', $retval))
        );
    }

    /**
     * Check if Topics exists. Realized for instance of UBL only.
     *
     * @return boolean      True if topics exist.
     */
    public function hasTopics()
    {
        $rvk = $this->getRvkWithMetadata();
        return
            parent::hasTopics()
            || (is_array($rvk) && count($rvk) > 0)
        ;
    }

    /**
     * Get specific marc information about topics. Unflexible solution
     * for UBL only implemented.
     *
     * @return array
     */
    public function getTopics()
    {
        return array_merge(
            $this->getAllSubjectHeadings(),
            $this->getAllSubjectHeadingsExtended()
        );
    }

    /**
     * Return all barcode of finc marc 983 $a at full marc record.
     *
     * @todo Method seems erroneous. Bugfixin needed.
     *
     * @return     array        List of barcodes.
     * @deprecated
     */
    public function getBarcode()
    {
        $barcodes = [];

        //$driver = ConnectionManager::connectToCatalog();
        $libraryCodes = $this->mainConfig->CustomIndex->LibraryGroup;

        // get barcodes from marc
        $barcodes = $this->getFieldArray('983', ['a']);

        if (!isset($libraryCodes->libraries)) {
            return $barcodes;
        } else {
            if (count($barcodes) > 0) {
                $codes = explode(",", $libraryCodes->libraries);
                $match = [];
                $retval = [];
                foreach ($barcodes as $barcode) {
                    if (preg_match('/^\((.*)\)(.*)$/', trim($barcode), $match));
                    if (in_array($match[1], $codes)) {
                        $retval[] = $match[2];
                    }
                } // end foreach
                if (count($retval) > 0) {
                    return $retval;
                }
            }
        }
        return [];
    }

    /**
     * Get the catalogue or opus number of a title. Implemented
     * for petrucci music library.
     *
     * @return array
     */
    protected function getCatalogueNumber()
    {
        return $this->getFieldArray('245', ['b']);
    }

    /**
     * Get the volume number
     *
     * @return array
     */
    protected function getVolume()
    {
        return $this->getFirstFieldValue('245', ['n']);
    }

    /**
     * Get Cartographic Mathematical Data
     *
     * @return array    Return multidimensional array with key of 'coordinates'
     *                  and 'scale'
     */
    public function getCartographicData()
    {

        // internal vars
        $retVal = [];
        $i = 0;
        // map of subfield to returning value key
        $mapper = ['a' => 'scale', 'c' => 'coordinates'];

        $fields = $this->getMarcRecord()->getFields('255');
        foreach ($fields as $f) {
            foreach ($mapper as $subfield => $key) {
                $sub = $f->getSubField($subfield);
                if ($sub) {
                    $retVal[$i][$key] = $sub->getData();
                }
            }
            $i++;
        }
        return $retVal;
    }

    /**
     * Get an array of content notes.
     *
     * @return array
     */
    protected function getContentNote()
    {
        return $this->getFieldArray('505', ['t']);
    }

    /**
     * Get dissertation notes for the record.
     *
     * @return array $retVal
     */
    public function getDissertationNote()
    {
        $retVal = [];
        $subFields = ['a','b','c','d','g'];
        $field = $this->getMarcRecord()->getFields('502');

        foreach ($field as $subfield) {
            foreach ($subFields as $fld) {
                $sfld = $subfield->getSubField($fld);
                if ($sfld) {
                    $retVal[$fld] = $sfld->getData();
                }
            }
        }
        return $retVal;
    }

    /**
     * Get id of related items
     *
     * @params boolean $allow_multiple_results
     *
     * @return string|array
     */
    protected function getRelatedItems($allow_multiple_results = false)
    {
        if ($allow_multiple_results) {
            return $this->getFieldArray('776', ['z']);
        } else {
            return $this->getFirstFieldValue('776', ['z']);
        }
    }

    /**
     * Get related records via search index
     *
     * @params int      $limit
     * @params string   $backend_id     Search engine
     *
     * @return array
     */
    protected function getRelatedRecords($limit, $backend_id = 'Solr')
    {
        $related = $this->getRelatedItems(true);

        if (empty($related)) {
            return [];
        }

        $query = new Query(
            'isbn' . ':' . implode(' OR ', $related)
            . ' AND NOT id:' . $this->getUniqueID()
        );

        $result = $this->searchService->search($backend_id, $query, 0, $limit);
        $return['first_results'] = $result->getRecords();
        if ($result->getTotal() > $limit) {
            $return['more_query'] = $query->getString();
        }
        return $return;
    }

    /**
     * Get RVK classification number with metadata from Marc records. Refs #599
     *
     * @return array
     */
    public function getRvkWithMetadata()
    {
        $array = [];

        $rvk = $this->getMarcRecord()->getFields('936');
        // if not return void value
        if (!$rvk) {
            return $array;
        } // end if
        foreach ($rvk as $key => $line) {
            // if subfield with rvk exists
            if ($line->getSubfield('a')) {
                // get rvk
                $array[$key]['rvk'] = $line->getSubfield('a')->getData();
                // get rvk nomination
                if ($line->getSubfield('b')) {
                    $array[$key]['name'] = $line->getSubfield('b')->getData();
                }
                if ($record = $line->getSubfields('k')) {
                    // iteration over rvk notation
                    foreach ($record as $field) {
                        $array[$key]['level'][] = $field->getData();
                    }
                } // end if subfield k
            } // end if subfield a
        } // end foreach
        return $array;
    }

    /**
     * Get an array of citations and references notes.
     *
     * @return array
     */
    public function getReferenceNotes()
    {
        return $this->getFieldArray('510');
    }

    /**
     * Get the publishers number and source of the record.
     *
     * @return array
     */
    public function getPublisherNumber()
    {
        return $this->getFieldArray('028', ['a', 'b']);
    }

    /**
     * Get the musical key of a piece (Marc 384).
     *
     * @return array
     */
    public function getMusicalKey()
    {
        return $this->getFieldArray('384');
    }

    /**
     * Get Mediennummer (media number) identifier for Bibliotheca ILS
     *
     * @returns items internal Bibliotheca-ID called "Mediennummer"
     * @deprecated Remove when Bibliotheca support ends
     */
    public function getMediennummer()
    {
        // loop through all existing LocalMarcFieldOfLibrary
        if ($fields = $this->getMarcRecord()->getFields(
            $this->getLocalMarcFieldOfLibrary())
        ) {
            foreach ($fields as $field) {
                // return the first occurance of $m
                $field = $field->getSubfield('a');
                if ($field) {
                    $matches = [];
                    if (preg_match('/\w+$/', $field->getData(), $matches)) {
                        return $matches[0];
                    }
                }
            }
        }
    }

    public function getTitleUniform()
    {
        $retval = [];
        foreach (['130','240'] as $pos => $field_name) {
            if ($field = $this->getMarcRecord()->getField($field_name)) {
                if ($field_name === '240') {
                    if ($field->getIndicator('1') === '0') {
                        //"Not printed or displayed"
                        continue;
                    }
                }
                foreach ([
                    'title' => 'a',
                    'lang' => 'g'
                         ] as $key => $sub_name) {
                    if ($line = $field->getSubfield($sub_name)) {
                        $retval[$key] = $line->getData();
                    }
                }
                return $retval;
            }
        }
        return $retval;
    }
}
