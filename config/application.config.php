<?php
return array(
	'modules' => array(
		'Core',
		'Blog',
		//'Album',
		//'Engine',
	),
    'service_manager' => array(
        'use_defaults' => true,
        'factories'    => array(
        ),
    ),
	'protected_module_namespace' => array(
		'Admin',	
	),
	'module_listener_options' => array( 
		'config_cache_enabled' => 0,
		'cache_dir'            => EVA_CONFIG_PATH . '/cache',
		'module_paths' => array(
			EVA_MODULE_PATH,
			EVA_ROOT_PATH . '/website',
		),
	),
);
