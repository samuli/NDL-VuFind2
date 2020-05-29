<?php
/**
 * Helper for dynamic lists.
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
 * @package  Search
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

use Vufind\ILS\Connection;
use VuFind\Record\Loader;
use Zend\Mvc\Controller\Plugin\Url;

/**
 * This helper renders dynamic lists to templates
 *
 * @category VuFind
 * @package  Controller
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class DynamicList extends \Zend\View\Helper\AbstractHelper
{
    protected $ils;

    protected $recordLoader;

    /**
     * URL helper
     *
     * @var Url
     */
    protected $urlHelper;

    /**
     * Constructor
     */
    public function __construct(Connection $ils, Loader $rl, Url $url)
    {
        $this->ils = $ils;
        $this->recordLoader = $rl;
        $this->urlHelper = $url;
    }

    /**
     * Invoke with query, no need for other parameters as 10
     * is maximum amount of items in this setting
     *
     * @param string $query to fetch
     */
    public function __invoke($params = [])
    {
        $result = $this->ils->checkFunction('getDynamicList', []);
        $type = $params['type'] ?? 'carousel';
        $query = $params['query'] ?? 'mostloaned';
        $amount = $params['amount'] ?? 10;
        $url = $result ? "/AJAX/JSON?method=dynamicList&type={$type}&query={$query}&amount={$amount}" : '';

        return $this->getView()->render('Helpers/dynamic-list.phtml', [
            'url' => $url
            ]);
    }
}
