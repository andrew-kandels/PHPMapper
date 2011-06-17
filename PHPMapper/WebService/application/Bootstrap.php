<?php
class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    protected function _initViewSettings()
    {
        date_default_timezone_set('America/Chicago');

        $this->_bootstrap('view');
        $this->_view = $this->getResource('view');
        $this->_view->doctype('XHTML1_STRICT');
        $this->_view->setEncoding('UTF-8');
        $this->_view->headTitle('Tendesk');
        $this->_view->headMeta()->appendHttpEquiv('Content-Type', 'text/html; charset=utf-8');
        $this->_view->headMeta()->appendHttpEquiv('Content-Language', 'en_US');
        $this->_view->headTitle()->setSeparator(' - ');
        /* includes */
        $this->_view->headScript()->appendFile('/js/lib.js');
        $this->_view->headLink()->appendStylesheet('/style.css');

        Zend_Session::setOptions(array('cookie_domain' => '.phpmapper.com'));
        Zend_Session::start();
    }

    protected function _initCache()
    {
        $cache = Zend_Cache::factory('Core', 'File',
            array(
                'lifetime' => 86400,
                'automatic_serialization' => true
            ),
            array(
                'cache_dir' => APPLICATION_ROOT . '/cache/'
            )
        );
        Zend_Registry::set('cache', $cache);
    }
}
