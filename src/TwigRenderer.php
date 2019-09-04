<?php

namespace Azt3k\SS\Twig;
use \SilverStripe\View\Requirements;

trait TwigRenderer {

    /**
     * @var bool
     */
    protected $includeRequirements = true;

    /**
     * [__get description]
     * @param  [type] $name [description]
     * @return [type]       [description]
     */
    public function __get($name) {

        if ($name == 'dic') {
            return $this->dic = new TwigContainer;
        } else {
            return parent::__get($name);
        }
    }

    /**
     * [__isset description]
     * @param  [type]  $name [description]
     * @return boolean       [description]
     */
    public function __isset($name) {

        return $this->hasMethod($name) ? false : true;
    }

    /**
     * Overrides the renderWith method for DOs
     * @param  [type] $templates    [description]
     * @param  [type] $customFields [description]
     * @return [type]               [description]
     */
    public function renderWith($templates, $customFields = null) {

        $data = ($this->customisedObject) ? $this->customisedObject : $this;

        if (is_array($customFields) || $customFields instanceof ViewableData) {
            $data = $data->customise($customFields);
        }

        if (!is_array($templates)) {
            $templates = [$templates];
        }

        try {
            return $this->renderTwig($templates, $data);
        } catch (\InvalidArgumentException $e) {
            return parent::renderWith($templates, $customFields);
        }

    }

    /**
     * [render description]
     * @param  [type] $params [description]
     * @return [type]         [description]
     */
    public function render($params = null) {

        $obj = ($this->customisedObj) ? $this->customisedObj : $this;
        if ($params) {
            $obj = $this->customise($params);
        }

        $action = method_exists($this, 'getAction')
            ? $this->getAction()
            : null;

        return $this->renderTwig(
            $this->getTemplateList($action),
            $obj
        );
    }

    protected function renderTwig($templates, $context) {
        $render = $this->getTwigTemplate($templates)->render([
            $this->dic['twig.controller_variable_name'] => $context
        ]);

        // inject any 'required' assets in the output, e.g. userforms JS
        if ($this->includeRequirements)
            $render = Requirements::includeInHTML($render);

        return $render;
    }

    public function customise($params) {

        if (is_array($params)) {
            foreach ($params as $key => $value) {
                $this->$key = $value;
            }
        }

        return $this;
    }

    protected function getTwigTemplate($templates) {

        $loader = $this->dic['twig.loader'];
        $extensions = $this->dic['twig.extensions'];
        $ret = $this->extend('ModifyTwigTemplates', $templates);
        if(is_array($ret) && count($ret) > 0) $templates = $ret[0];

        if(!is_array($templates) || count($templates) == 0) {
            throw new \InvalidArgumentException("No templates available, perhaps the extension if borked ");
        }

        foreach ($templates as $value) {

            // catches scenarios when a template is supplied as:
            // Array
            // (
            //     [type] => Includes
            //     [0] => SilverStripe\Security\Security_login
            // )
            if (is_array($value)) $value = $value[0];

            $ret = $this->extend('ModifyTwigTemplate', $value);
            if(is_array($ret) && count($ret) && is_string($ret[0])) {
                $value = $ret[0];
            }

            foreach ($extensions as $extension) {

                if ($loader->exists($value . $extension)) {
                    return $this->dic['twig']->loadTemplate($value . $extension);
                }
            }
        }
        throw new \InvalidArgumentException("No templates for " . print_r($templates, 1) . " exist");
    }

    /**
     * [buildTemplatesFromClassName description]
     * @param  [type] $className [description]
     * @param  [type] $action    [description]
     * @return [type]            [description]
     */
    public function buildTemplatesFromClassName($className, $action = null) {

        // init templates
        $templates = [];

        // Add action-specific templates for inheritance chain
        if ($action && $action != 'index') {
            $parentClass = $className;
            while (
                $parentClass &&
                $parentClass != 'SilverStripe\Control\Controller' &&
                $parentClass != 'SilverStripe\ORM\DataObject'
            ) {
                $classPath = str_replace('\\', '/', $parentClass);
                $templates[] = $classPath . '_' . $action;
                $parentClass = get_parent_class($parentClass);
            }
        }

        // Add controller templates for inheritance chain
        $parentClass = $className;
        while (
            $parentClass &&
            $parentClass != 'SilverStripe\Control\Controller' &&
            $parentClass != 'SilverStripe\ORM\DataObject'
        ) {
            $classPath = str_replace('\\', '/', $parentClass);
            $templates[] = $classPath;
            $parentClass = get_parent_class($parentClass);
        }

        return $templates;
    }

    /**
     * [getTemplateList description]
     * @param  [type] $action [description]
     * @return [type]         [description]
     */
    protected function getTemplateList($action = null) {

        // Hard-coded templates
        if (!empty($this->templates[$action])) {
            $templates = $this->templates[$action];
        } elseif (!empty($this->templates['index'])) {
            $templates = $this->templates['index'];
        } elseif (!empty($this->template)) {
            $templates = $this->template;
        } else {
            // build template list
            // get_class and $this->className return different things sometimes
            $templates = array_unique(array_merge(
                $this->buildTemplatesFromClassName(get_class($this), $action),
                $this->buildTemplatesFromClassName($this->ClassName, $action)
            ));
        }

        // if the current class has a getHTMLTemplate method try it
        if (method_exists($this, 'getHTMLTemplate')) {
            $templates = array_unique(array_merge(
                [$this->getHTMLTemplate()],
                $templates
            ));
        }

        return $templates;
    }

}
