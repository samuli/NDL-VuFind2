<?php
/**
 * Row Definition for user_list
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @package  Db_Row
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Db\Row;

use VuFind\Exception\ListPermission as ListPermissionException;
use VuFind\Exception\MissingField as MissingFieldException;

/**
 * Row Definition for user_list
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class UserList extends \VuFind\Db\Row\UserList
{
    /**
     * Session container for last list information.
     *
     * @var Container
     */
    protected $tagParser = null;

    /**
     * Constructor
     *
     * @param \Zend\Db\Adapter\Adapter $adapter Database adapter
     * @param Container                $session Session container
     */
    public function setTagParser($tagParser)
    {
        $this->tagParser = $tagParser;
    }

    /**
     * Saves the properties to the database.
     *
     * This performs an intelligent insert/update, and reloads the
     * properties with fresh data from the table on success.
     *
     * @param \VuFind\Db\Row\User|bool $user Logged-in user (false if none)
     *
     * @return mixed The primary key value(s), as an associative array if the
     *     key is compound, or a scalar if the key is single-column.
     * @throws ListPermissionException
     * @throws MissingFieldException
     */
    public function save($user = false)
    {
        $this->finna_updated = date('Y-m-d H:i:s');
        return parent::save($user);
    }

    public function updateFromRequest($user, $request)
    {
        $id = parent::updateFromRequest($user, $request);
        $linker = $this->getDbTable('resourcetags');
        $linker->destroyLinks(null, $user->id, $this->id);
        if ($tags = $request->get('listtags')) {
            foreach ($this->tagParser->parse(implode(' ', $tags)) as $tag) {
                $this->addListTag($tag, $user);
            }
        }
        return $this->id;
    }

    /**
     * Get an array of tags associated with this list.
     *
     * @return array
     */
    public function getListTags()
    {
        $table = $this->getDbTable('User');
        $user = $table->select(['id' => $this->user_id])->current();
        if (empty($user)) {
            return [];
        }
        return $user->getTags(null, $this->id);
    }

    /**
     * Add a tag to the list.
     *
     * @param string              $tagText The tag to save.
     * @param \VuFind\Db\Row\User $user    The user posting the tag.
     * @param string              $list_id The list associated with the tag
     * (optional).
     *
     * @return void
     */
    public function addListTag($tagText, $user)
    {
        $tagText = trim($tagText);
        if (!empty($tagText)) {
            $tags = $this->getDbTable('tags');
            $tag = $tags->getByText($tagText);
            $linker = $this->getDbTable('resourcetags');
            $linker->createLink(
                null, $tag->id, is_object($user) ? $user->id : null, $this->id
            );
        }
    }
}
