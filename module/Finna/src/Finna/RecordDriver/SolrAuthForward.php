<?php
/**
 * Model for Forward authority records in Solr.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2013-2019.
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
 * Model for Forward authority records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrAuthForward extends SolrAuthDefault
{
    use XmlReaderTrait;

    /**
     * Return description
     *
     * @return string|null
     */
    public function getSummary()
    {
        $doc = $this->getMainElement();
        if (isset($doc->BiographicalNote)) {
            foreach ($doc->BiographicalNote as $bio) {
                $txt = (string)$bio;
                $attr = $bio->attributes();
                if (isset($attr->{'henkilo-biografia-tyyppi'})
                    && (string)$attr->{'henkilo-biografia-tyyppi'} === 'biografia'
                ) {
                    return [strip_tags((string)$bio)];
                }
            }
        }
        return null;
    }

    /**
     * Get the main metadata element
     *
     * @return SimpleXMLElement
     */
    protected function getMainElement()
    {
        $nodes = (array)$this->getXmlRecord()->children();
        $node = reset($nodes);
        return is_array($node) ? reset($node) : $node;
    }
}
