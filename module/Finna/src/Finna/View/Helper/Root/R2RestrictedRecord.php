<?php
/**
 * Helper class for restricted Solr R2 records.
 *
 * - For local index records: handles linking to alternative record with
 *   restricted metadata in R2 index.
 * - For R2 records: handles permission related REMS actions
 *   (user registration, permission requests, checking of user permissions).
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019.
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
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

use Finna\RemsService\RemsService;

/**
 * Helper class for restricted Solr R2 records.
 *
 * - For local index records: handles linking to alternative record with
 *   restricted metadata in R2 index.
 * - For R2 records: handles permission related REMS actions
 *   (user registration, permission requests, checking of user permissions).
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class R2RestrictedRecord extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Is R2 search enabled?
     *
     * @var bool
     */
    protected $enabled;

    /**
     * REMS service
     *
     * @var RemsService
     */
    protected $rems;

    /**
     * Is the user authorized to use REMS?
     *
     * @var bool
     */
    protected $authorized;

    /**
     * Base url for R2 records.
     *
     * @var null|string
     */
    protected $r2RecordBaseUrl;

    /**
     * Constructor
     *
     * @param bool                $enabled         Is R2 enabled?
     * @param \Zend\Config\Config $config          VuFind configuration
     * @param RemsService         $rems            REMS service
     * @param bool                $authorized      Is the user authorized to
     * use REMS?
     * @param null|string         $r2RecordBaseUrl Base url for R2
     * records.
     */
    public function __construct(
        bool $enabled,
        \Zend\Config\Config $config,
        RemsService $rems,
        bool $authorized,
        $r2RecordBaseUrl = null
    ) {
        $this->enabled = $enabled;
        $this->rems = $rems;
        $this->authorized = $authorized;
        $this->r2RecordBaseUrl = trim($r2RecordBaseUrl);
    }

    /**
     * Render info box.
     *
     * @param RecordDriver $driver Record driver
     * @param array        $params Parameters
     *
     * @return null|html
     */
    public function __invoke($driver, $params = null)
    {
        if (!$this->isAvailable()) {
            return null;
        }

        if ($restricted = $driver->getRestrictedAlternative()) {
            // Local index record with a restricted alternative in R2 index
            // (possibly in another view).
            $route = $restricted['route'];

            $urlHelper = $this->getView()->plugin('url');
            $recUrl = $urlHelper->__invoke($route, ['id' => $restricted['id']]);

            if ($this->r2RecordBaseUrl) {
                // R2 records are located in another view.

                // Prepend base url to record url
                $baseUrl
                    = $urlHelper->__invoke('home', [], ['force_canonical' => true]);
                $parts = parse_url($baseUrl);
                $path = $parts['path'] ?? '';

                // Make sure that base url has currect scheme
                $baseParts = parse_url($this->r2RecordBaseUrl);
                $base = 'https://' . ($baseParts['host'] ?? '') . $baseParts['path'];
                // and remove trailing '/'
                if (substr($base, -1) === '/') {
                    $base = substr($base, 0, -1);
                }
                $recUrl = $base . '/' . substr($recUrl, strlen($path));
            }

            return $this->getView()->render(
                'Helpers/R2RestrictedRecordNote.phtml', ['recordUrl' => $recUrl]
            );
        } elseif ($driver->hasRestrictedMetadata()) {
            $user = $params['user'] ?? null;
            $autoOpen = $params['autoOpen'] ?? false;
            $restrictedMetadataIncluded = $driver->isRestrictedMetadataIncluded();
            $accessStatus = $this->rems->getAccessPermission();
            $preventApplicationSubmit = $restrictedMetadataIncluded
                || $accessStatus === RemsService::STATUS_SUBMITTED;
            $applicationSubmitted = $accessStatus === RemsService::STATUS_SUBMITTED;

            // R2 record with restricted metadata
            $params = [
                'weakLogin' => $user && !$this->authorized,
                'user' => $user,
                'autoOpen' => $autoOpen,
                'id' => $driver->getUniqueID(),
                'collection' => $driver->isCollection(),
                'restrictedMetadataIncluded' => $restrictedMetadataIncluded,
                'preventApplicationSubmit' => $preventApplicationSubmit,
                'applicationSubmitted' => $applicationSubmitted,
                'formId' => 'R2Register',
            ];

            return $this->getView()->render(
                'Helpers/R2RestrictedRecordPermission.phtml', $params
            );
        }

        return null;
    }

    /**
     * Check if R2 is available
     *
     * @return bool
     */
    public function isAvailable()
    {
        return (bool)$this->enabled;
    }
}
