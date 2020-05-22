<?php

namespace Finna\View\Helper\Root;

use \Vufind\ILS\Connection;

class DynamicList extends \Zend\View\Helper\AbstractHelper
{

    protected $ils;

    /**
     * Constructor
     */
    public function __construct(Connection $ils)
    {
        $this->ils = $ils;
    }

    /**
     * Invoke with query, no need for other parameters as 10
     * is maximum amount of items in this setting
     * 
     * @param string $query to fetch 
     */
    public function __invoke($query = 'mostloaned')
    {
        $result = $this->ils->checkFunction('getDynamicList', []);
        if (!$result) {
            return '';
        }
        $records = $this->ils->getDynamicList(['query' => $query]);

        return $records;
    }
}