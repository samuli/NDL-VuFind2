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

/**
 * This helper renders dynamic lists to templates
 *
 * @category VuFind
 * @package  Controller
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class TitleList extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Invoke with query, no need for other parameters as 20
     * is maximum amount of items in this setting
     *
     * @param array $params to get dynamic lists
     *
     * @return ViewModel
     */
    public function __invoke($params = [])
    {
        $type = $params['type'] ?? 'carousel';
        $query = $params['query'] ?? 'new';
        $amount = $params['amount'] ?? 20;
        $amount = $amount > 20 ? 20 : $amount;

        $id = $params['id'] ?? '';
        $url = "/AJAX/JSON?method=titleList" .
                "&type={$type}&query={$query}&amount={$amount}&id={$id}";

        return $this->getView()->render(
            'Helpers/title-list.phtml', compact('url', 'type')
        );
    }
}
