<?php
/**
 * Row Definition for user
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
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Db\Row;
use VuFind\Db\Table\Tags as TagsTable, VuFind\Db\Table\UserList as UserListTable,
    VuFind\Db\Table\UserResource as UserResourceTable,
    Zend\Db\RowGateway\RowGateway, Zend\Db\Sql\Expression,
    Zend\Db\Sql\Predicate\Predicate, Zend\Db\Sql\Sql,
    Zend\Db\TableGateway\Feature\GlobalAdapterFeature;

/**
 * Row Definition for user
 *
 * @category VuFind2
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class User extends RowGateway
{
    /**
     * Constructor
     *
     * @param \Zend\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'user', $adapter);
    }

    /**
     * Saves the properties to the database.
     *
     * This performs an intelligent insert/update, and reloads the
     * properties with fresh data from the table on success.
     *
     * @return mixed The primary key value(s), as an associative array if the
     *     key is compound, or a scalar if the key is single-column.
     */
    public function save()
    {
        // Since this object is frequently stored in the session, we should
        // reconnect to the database as part of the save action to prevent
        // exceptions:
        $this->sql = new Sql(GlobalAdapterFeature::getStaticAdapter(), $this->table);
        return parent::save();
    }

    /**
     * Save ILS login credentials.
     *
     * @param string $username Username to save
     * @param string $password Password to save
     *
     * @return mixed           The output of the save method.
     */
    public function saveCredentials($username, $password)
    {
        $this->cat_username = $username;
        $this->cat_password = $password;
        return $this->save();
    }

    /**
     * Change home library.
     *
     * @param string $homeLibrary New home library to store.
     *
     * @return mixed           The output of the save method.
     */
    public function changeHomeLibrary($homeLibrary)
    {
        $this->home_library = $homeLibrary;
        return $this->save();
    }

    /**
     * Get a list of all tags generated by the user in favorites lists.  Note that
     * the returned list WILL NOT include tags attached to records that are not
     * saved in favorites lists.
     *
     * @param string $resourceId Filter for tags tied to a specific resource (null
     * for no filter).
     * @param int    $listId     Filter for tags tied to a specific list (null for no
     * filter).
     * @param string $source     Filter for tags tied to a specific record source.
     *
     * @return Zend_Db_Table_Rowset
     */
    public function getTags($resourceId = null, $listId = null, $source = 'VuFind')
    {
        $userId = $this->id;
        $callback = function ($select) use ($userId, $resourceId, $listId, $source) {
            $select->columns(
                array(
                    'id' => new Expression(
                        'min(?)', array('tags.id'),
                        array(Expression::TYPE_IDENTIFIER)
                    ),
                    'tag',
                    'cnt' => new Expression(
                        'COUNT(DISTINCT(?))', array('rt.resource_id'),
                        array(Expression::TYPE_IDENTIFIER)
                    )
                )
            );
            $select->join(
                array('rt' => 'resource_tags'), 'tags.id = rt.tag_id', array()
            );
            $select->join(
                array('r' => 'resource'), 'rt.resource_id = r.id', array()
            );
            $select->join(
                array('ur' => 'user_resource'), 'r.id = ur.resource_id', array()
            );
            $select->group(array('tag'))
                ->order(array('tag'));

            $select->where->equalTo('ur.user_id', $userId)
                ->equalTo('rt.user_id', $userId)
                ->equalTo(
                    'ur.list_id', 'rt.list_id',
                    Predicate::TYPE_IDENTIFIER, Predicate::TYPE_IDENTIFIER
                )
                ->equalTo('r.source', $source);

            if (!is_null($resourceId)) {
                $select->where->equalTo('r.record_id', $resourceId);
            }
            if (!is_null($listId)) {
                $select->where->equalTo('rt.list_id', $listId);
            }
        };

        $table = new TagsTable();
        return $table->select($callback);
    }

    /**
     * Same as getTags(), but returns a string for use in edit mode rather than an
     * array of tag objects.
     *
     * @param string $resourceId Filter for tags tied to a specific resource (null
     * for no filter).
     * @param int    $listId     Filter for tags tied to a specific list (null for no
     * filter).
     * @param string $source     Filter for tags tied to a specific record source.
     *
     * @return string
     */
    public function getTagString($resourceId = null, $listId = null,
        $source = 'VuFind'
    ) {
        $myTagList = $this->getTags($resourceId, $listId, $source);
        $tagStr = '';
        if (count($myTagList) > 0) {
            foreach ($myTagList as $myTag) {
                if (strstr($myTag->tag, ' ')) {
                    $tagStr .= "\"$myTag->tag\" ";
                } else {
                    $tagStr .= "$myTag->tag ";
                }
            }
        }
        return trim($tagStr);
    }

    /**
     * Get all of the lists associated with this user.
     *
     * @return Zend_Db_Table_Rowset
     */
    public function getLists()
    {
        $userId = $this->id;
        $callback = function ($select) use ($userId) {
            $select->columns(
                array(
                    '*',
                    'cnt' => new Expression(
                        'COUNT(DISTINCT(?))', array('ur.resource_id'),
                        array(Expression::TYPE_IDENTIFIER)
                    )
                )
            );
            $select->join(
                array('ur' => 'user_resource'), 'user_list.id = ur.list_id',
                array(), $select::JOIN_LEFT
            );
            $select->where->equalTo('user_list.user_id', $userId);
            $select->group(
                array('id', 'user_id', 'title', 'description', 'created', 'public')
            );
            $select->order(array('title'));
        };

        $table = new UserListTable();
        return $table->select($callback);
    }

    /**
     * Get information saved in a user's favorites for a particular record.
     *
     * @param string $resourceId ID of record being checked.
     * @param int    $listId     Optional list ID (to limit results to a particular
     * list).
     * @param string $source     Source of record to look up
     *
     * @return array
     */
    public function getSavedData($resourceId, $listId = null, $source = 'VuFind')
    {
        $table = new UserResourceTable();
        return $table->getSavedData($resourceId, $source, $listId, $this->id);
    }

    /**
     * Check that the user's password matches the provided value.
     *
     * @param string $password Password to check.
     *
     * @return bool
     */
    public function checkPassword($password)
    {
        // Simple check for now since we store passwords in the database; we may
        // eventually want to replace this with some kind of hash mechanism for
        // better security.
        return $this->password == $password;
    }
    /**
     * Add/update a resource in the user's account.
     *
     * @param VuFind_Model_Db_ResourceRow $resource        The resource to add/update
     * @param VuFind_Model_Db_UserListRow $list            The list to store the
     * resource in.
     * @param array                       $tagArray        An array of tags to
     * associate with the resource.
     * @param string                      $notes           User notes about the
     * resource.
     * @param bool                        $replaceExisting Whether to replace all
     * existing tags (true) or append to the existing list (false).
     *
     * @return void
     */
    public function saveResource(
        $resource, $list, $tagArray, $notes, $replaceExisting = true
    ) {
        // Create the resource link if it doesn't exist and update the notes in any
        // case:
        $linkTable = new UserResourceTable();
        $linkTable->createOrUpdateLink($resource->id, $this->id, $list->id, $notes);

        // If we're replacing existing tags, delete the old ones before adding the
        // new ones:
        if ($replaceExisting) {
            $resource->deleteTags($this, $list->id);
        }

        // Add the new tags:
        foreach ($tagArray as $tag) {
            $resource->addTag($tag, $this, $list->id);
        }
    }

    /**
     * Given an array of item ids, remove them from all lists
     *
     * @param array  $ids    IDs to remove from the list
     * @param string $source Type of resource identified by IDs
     *
     * @return void
     */
    public function removeResourcesById($ids, $source = 'VuFind')
    {
        /* TODO
        // Retrieve a list of resource IDs:
        $resourceTable = new VuFind_Model_Db_Resource();
        $resources = $resourceTable->findResources($ids, $source);

        $resourceIDs = array();
        foreach ($resources as $current) {
            $resourceIDs[] = $current->id;
        }

        // Remove Resource (related tags are also removed implicitly)
        $userResourceTable = new VuFind_Model_Db_UserResource();
        $userResourceTable->destroyLinks($resourceIDs, $this->id);\
         */
    }

    /**
     * Destroy the user.
     *
     * @return int The number of rows deleted.
     */
    public function delete()
    {
        // Remove all lists owned by the user:
        $lists = $this->getLists();
        foreach ($lists as $current) {
            // The rows returned by getLists() are read-only, so we need to retrieve
            // a new object for each row in order to perform a delete operation:
            $list = UserListTable::getExisting($current->id);
            $list->delete($this, true);
        }

        // Remove the user itself:
        return parent::delete();
    }
}
