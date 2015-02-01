<?php

namespace APP\CONTROLLERS;

/**
 * Class BaseController
 * @package APP\CONTROLLERS
 */
class BaseController
{

    private $twig;
    protected $f3;
    protected $web;
    protected $action;
    protected $controller;
    protected $method;
    public $layout = 'layout';

    /**
     * Return in all Child Constructor $twig, $f3, $web
     */
    public function __construct()
    {
        $this->f3   = \Base::instance();
        $this->web  = \Web::instance();
        $this->twig = $this->f3->get('TWIG');
        $this->getTpl();
    }

    /**
     * @param string | Bool $file true: routeTpl | false: Json | else: Name of the Twig File
     * @param array $values Values inject in the View
     */
    protected function render($file, $values = [])
    {
        if($file === true){
            $tpl = $this->controller.'/'.$this->action.'.twig';
        }elseif($file === false){
            echo json_encode($values);
            return;
        } else {
            $tpl = $file;
        }
        $values['layout'] = $this->layout;
        echo $this->twig->render($tpl, $values);
    }


    /**
     * Get the targeted template by url
     * Set as protected $action, $controller & $method
     */
    private function getTpl()
    {

        $this->method = $this->f3['SERVER']['REQUEST_METHOD'];

        $params = $this->f3['PARAMS'];
        $base = $this->f3['PARAMS'][0];
        if (count($this->f3['PARAMS']) > 1) {
            unset($params[0]);
            foreach ($params as $k => $v) {
                $base = str_replace('/' . $v, '', $base);
                $index = $base . '/@' . $k;
            }
        } else {
            $index = $base;
        }

        foreach ($this->f3['ROUTES'] as $key => $value) {

            $trunc = explode('/@', $key);
            if ($trunc[0] == $base) {
                $innerRoute         = $this->f3['ROUTES'][$index][3][$this->method][0];
                $first              = explode('->', $innerRoute);
                $this->action       = $first[1];
                $second             = explode('\\', $first[0]);
                $third              = explode('Controller', $second[2]);
                $this->controller   = strtolower($third[0]);
                break;
            }
        }
    }


}