<?php

namespace Azt3k\SS\Twig;

class TwigContainer extends \Pimple
{
    /**
     * Default config of properties
     * @var array
     */
    protected static $config = [
        'twig.loader_class' => 'Twig_Loader_Filesystem',
        'twig.environment_options' => array(
            'auto_reload' => true
        ),
        'twig.extensions' => array(
            '.twig'
        ),
        'twig.controller_variable_name' => 'c',
        "twig.globals"  => [
            'imagepath' => 'asdasd'
        ],
        "twig.module_template_paths" => [

        ]
    ];

    /**
     * Holds user configured extensions of services
     * @var array
     */
    protected static $extensions = [];

    /**
     * Holds user configured shared services
     * @var array
     */
    protected static $shared = [];

    /**
     * Constructs the container and set up default services and properties
     */
    public function __construct() {

        parent::__construct();

        //Shared services
        $this['twig'] = $this->share(function ($c) {

            $envOptions = array_merge(
                ['cache' => $c['twig.compilation_cache']],
                $c['twig.environment_options']
            );

            $twig = new \Twig_Environment(
                $c['twig.loader'],
                $envOptions
            );

            if (isset($c['twig.globals'])) {
                if (isset($c['twig.globals'])) {
                    foreach ($c['twig.globals'] as $global => $path) {
                        $twig->addGlobal($global, $path);
                    }
                }
            }

            if (isset($envOptions['debug']) && $envOptions['debug'])
                $twig->addExtension(new \Twig_Extension_Debug());

            return $twig;

        });

        $this['twig.loader'] = $this->share(function ($c) {
            $twigLoader = new $c['twig.loader_class']($c['twig.template_paths']);
            if (isset($c['twig.template_namespaced_paths'])) {
                foreach ($c['twig.template_namespaced_paths'] as $path => $namespace) {
                    $twigLoader->addPath($path, $namespace);
                }
            }

            return $twigLoader;
        });

        // Dynamic props
        $this['twig.compilation_cache'] = TEMP_FOLDER . '/twig-cache';

        // create some paths to check
        $actualPaths = [];
        $possiblePaths = [
            (
                THEMES_PATH . '/' .
                \SilverStripe\Core\Config\Config::inst()->get('SilverStripe\View\SSViewer', 'theme') . '/' .
                'twig'
            ),
            BASE_PATH . '/app/twig',
            BASE_PATH . '/app/templates',
            BASE_PATH . '/node_modules'
        ];

        // code to generate paths for templates from modules
        // $modules = \SilverStripe\Core\Manifest\ModuleLoader::inst()
        //     ->getManifest()->getModules();
        // foreach ($modules as $module) {
        //     $possiblePaths[] = $module->getPath() . '/templates';
        // }

        // modules inject template paths via the following property
        $possiblePaths = array_merge(self::$config['twig.module_template_paths'], $possiblePaths);

        // add paths
        foreach ($possiblePaths as $path)
            if (is_dir($path)) $actualPaths[] = $path;

        // add the paths to the conf
        $this['twig.template_paths'] = $actualPaths;

        // Default config
        foreach (self::$config as $key => $value) $this[$key] = $value;

        // Extensions
        if (is_array(self::$extensions))
            foreach (self::$extensions as $value)
                $this->extend($value[0], $value[1]);

        // Shared
        if (is_array(self::$shared))
            foreach (self::$shared as $value)
                $this[$value[0]] = $this->share($value[1]);

    }

    /**
     * Alows the extending of already defined services by the user
     * @param string  $name      Name of service
     * @param Closure $extension Extending function
     */
    public static function addExtension($name, $extension) {
        self::$extensions[] = array($name, $extension);
    }

    /**
     * Allows the adding of a shared service by the user
     * @param string  $name   Name of service
     * @param Closure $shared The shared service function
     */
    public static function addShared($name, $shared) {
        self::$shared[] = array($name, $shared);
    }

    /**
     * Allows the addition to the default config by the user
     * @param array $config The extending config
     */
    public static function extendConfig($config) {
        if (is_array($config)) {
            self::$config = array_merge(self::$config, $config);
        }
    }

}
