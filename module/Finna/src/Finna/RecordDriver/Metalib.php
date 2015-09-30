<?php
/**
 * Model for Primo Central records.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2012-2015.
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
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for Primo Central records.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class Metalib extends \VuFind\RecordDriver\SolrMarc
{
    use SolrFinna;

    protected $sourceIdentifier = 'Metalib';


    public function getFields()
    {
        return $this->fields;
    }

    /**
     * Get the item's source.
     *
     * @return array
     */
    public function getSource()
    {
        return $this->fields['source'] ?: null;
    }

    public function getHierarchyParentId()
    {
        return false;
    }

    public function getContainerReference()
    {
        $parts = explode(',', $this->getIsPartOf(), 2);
        return isset($parts[1]) ? trim($parts[1]) : '';
    }

    /**
     * Get the item's "is part of".
     *
     * @return string
     */
    public function getIsPartOf()
    {
        return isset($this->fields['ispartof'])
            ? $this->fields['ispartof'] : '';
    }

    
    public function getDeduplicatedAuthors()
    {
        return [];
    }

    public function getOnlineURLs($raw = false)
    {
        return [];
    }
}
