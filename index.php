<?php

require_once('libs/hermosa.php');

########################################
##  SETTINGS
########################################
$settings = array(
	'environment' => array(
		'local' => array('localhost', '10.1.1'),
		'live' => '',
	),
	'config' => array(
		'db' => array(
			'local' => array(
				'host' => 'localhost',
				'db' => '',
				'user' => '',
				'pass' => ''
			),
			'live' => array(
				'host' => '',
				'db' => '',
				'user' => '',
				'pass' => ''
			),
		),
		'auth' => array(
			'default' => array(
				'realm' => 'Test Realm',
				'accounts' => array(
					'test' => 'test',
					'test2' => 'test2',
				),
			),
		)
	),
);

########################################
##  ACTIONS
########################################


get('^/?$', function() {
	return conf('db.host');
});


get('^restricted/?$', function() {
	$user = basic_auth('test2');
	return $user;
	
});

get('test/(\d+)', function($id) {
	return conf(array('db', 'host'));
});

########################################
run($settings);
########################################
