<?php

namespace Finna\Controller;

use Zend\ServiceManager\ServiceLocatorInterface;

class DynamicListController extends \VuFind\Controller\AbstractBase
{

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm        Service manager
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        parent::__construct($sm);
    }


    public function resultsAction()
    {
        $catalog = $this->getILS();
        $params = $this->getRequest()->getQuery()->toArray();
        var_dump($params);
        $view = $this->createViewModel();
        $view->setTemplate('dynamiclist/results.phtml');
        return $view;
    }
}