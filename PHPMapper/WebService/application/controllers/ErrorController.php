<?php
class ErrorController extends Zend_Controller_Action
{
    public function errorAction()
    {
        $this->getResponse()->appendBody('ERROR');

        $content = null;
        $errors = $this->_getParam('error_handler') ;
        $exception = $errors->exception;
        $this->view->assign('exception', $exception);

        switch ($errors->type)
        {
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:

                // 404 error -- controller or action not found
                $this->getResponse()->setRawHeader('HTTP/1.1 404 Not Found');
                $this->view->message = <<<EOH
<h1>404 Page not found</h1>
<p>The page you requested was not found.</p>
EOH;
                break;

            default:
                // application error; display error page, but don't change
                // status code
                $this->view->message = <<<EOH
<h1>Error</h1>
<p>An unexpected error occurred with your request. Please try again later.</p>
EOH;
                break ;
        }

        // Clear previous content
        $this->getResponse()->clearBody();
        $this->view->content = $content;
    }
}

