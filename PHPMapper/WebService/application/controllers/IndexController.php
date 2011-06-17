<?php
class IndexController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $obj = new PHPMapper('us');

        foreach ($this->getRequest()->getParams() as $param => $value) {
            if (preg_match('/^[A-Z]{2}$/', $param)) {
                $obj->set('US', strtoupper($param), (int) $value);
            }
        }

        $this->view->map = $obj;
    }

    public function __call($method, $args)
    {
        if (preg_match('/Action$/', $method)) {
            $this->_forward('index');
        }
    }
}
