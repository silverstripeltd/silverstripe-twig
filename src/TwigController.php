<?php

namespace Azt3k\SS\Twig;

trait TwigController {

    use TwigRenderer;

    public function __get($name)
    {
        if ($name == 'dic') {
            return $this->dic = new TwigContainer;
        } else {
            return parent::__get($name);
        }
    }

    public function __isset($name)
    {
        return $this->hasMethod($name) ? false : true;
    }

    public function handleAction($request, $action)
    {
        // urlParams, requestParams, and action are set for backward compatability
        foreach ($request->latestParams() as $k => $v) {
            if($v || !isset($this->urlParams[$k])) $this->urlParams[$k] = $v;
        }

        $this->action = str_replace("-","_",$action);
        $this->requestParams = $request->requestVars();
        if(!$this->action) $this->action = 'index';

        if (!$this->hasAction($this->action)) {
            $this->httpError(404, "The action '$this->action' does not exist in class " . get_class($this));
        }

        // run & init are manually disabled, because they create infinite loops and other dodgy situations
        if (!$this->checkAccessAction($this->action) || in_array(strtolower($this->action), array('run', 'init'))) {
            return $this->httpError(403, "Action '$this->action' isn't allowed on class " . get_class($this));
        }

        if ($this->hasMethod($this->action)) {
            $result = $this->{$this->action}($request);

            // If the action returns an array, customise with it before rendering the template.
            if (is_array($result)) {
                return $this->renderTwig($this->getTemplateList($this->action), $this->customise($result));
            } else {
                return $result;
            }
        } else {
            return $this->renderTwig($this->getTemplateList($this->action), $this);
        }
    }
}
