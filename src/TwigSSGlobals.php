<?php

namespace Azt3k\SS\Twig;

use SilverStripe\Core\ClassInfo;
use SilverStripe\View\ViewableData;
use SilverStripe\View\TemplateGlobalProvider;

class TwigSSGlobals
{

    protected $globals;

    /**
     * Loads the global vars
     */
    public function __construct() {

        // add SS globals in to the global obj
        $implementors = ClassInfo::implementorsOf(TemplateGlobalProvider::class);
        $globals = [];
        if ($implementors) {
            foreach ($implementors as $implementor) {

                // Create a new instance of the object for method calls
                $implementor = new $implementor();
                $exposedVariables = $implementor->get_template_global_variables();

                foreach ($exposedVariables as $varName => $details) {
                    if (!is_array($details)) {
                        $details = [
                            'method' => $details,
                            'casting' => ViewableData::config()->uninherited('default_cast')
                        ];
                    }

                    // If just a value (and not a key => value pair), use method name for both key and value
                    if (is_numeric($varName)) {
                        $varName = $details['method'];
                    }

                    // Add in a reference to the implementing class (might be a string class name or an instance)
                    $details['implementor'] = $implementor;

                    // And a callable array
                    if (isset($details['method'])) {
                        $details['callable'] = [$implementor, $details['method']];
                    }

                    // Save with both uppercase & lowercase first letter, so either works
                    $lcFirst = strtolower($varName[0]) . substr($varName, 1);

                    $globals[$lcFirst] = $details;
                    $globals[ucfirst($varName)] = $details;
                }
            }
        }

        $this->globals = $globals;
    }

    /**
     * Test for the property the customer is interested in
     *
     * @param  String $name Property name
     * @return Boolean      Whether it exists or not
     */
    public function __isset($name) {
        return isset($this->globals[$name]);
    }

    /**
     * Get the property the customer is interested
     *
     * @param  String $name Property name
     * @return Mixed       The property requested or null
     */
    public function __get($name) {

        // test if exist
        if ($this->globals[$name]) {

            // return if cached
            if (isset($this->globals[$name]['instance'])) {
                return $this->globals[$name]['instance'];

                // load based off of all the fun things
            } else {
                $property = $this->globals[$name];
                if ($property['callable']) {
                    $inst = $property['callable'][0]->{$property['callable'][1]}();
                } else {
                    throw new \LogicException('
                        API for non callable variables is unknown
                    ');
                }
                // cache the result so we don't do the lookup again
                $this->globals[$name]['instance'] = $inst;
                return $inst;
            }

            // return null if it doesn't exist
        } else {
            return null;
        }
    }



}
