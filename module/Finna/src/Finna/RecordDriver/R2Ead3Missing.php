<?php
/**
 * Model for missing R2 records
 * (created when attempting to load a non-existing record or a
 * record that the user is not authorized to see).
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for missing R2 records
 * (created when attempting to load a non-existing record or a
 * record that the user is not authorized to see).
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class R2Ead3Missing extends R2Ead3
{
    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->translate('R2_restricted_record_title');
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
        // Add url to record page so that it gets included in exported data.
        return [[
            'route' => 'r2record',
            'routeParams' => ['id' => $this->getUniqueID()]]
        ];
    }

    /**
     * Does this record contain restricted metadata?
     *
     * @return bool
     */
    public function hasRestrictedMetadata()
    {
        return true;
    }

    /**
     * Is restricted metadata included with the record, i.e. is the user
     * authorized to access restricted metadata?
     *
     * @return bool
     */
    public function isRestrictedMetadataIncluded()
    {
        return false;
    }

    /**
     * Show organisation menu on record page?
     *
     * @return boolean
     */
    public function showOrganisationMenu()
    {
        return false;
    }
}
