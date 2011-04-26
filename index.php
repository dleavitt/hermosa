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
				),
			),
		)
	),
);

########################################
##  ACTIONS
########################################

// TOP 10 LIST
function action_index()
{
	return conf('db.host');
	// $pdo = pdo_connect();
	// 
	// $query = $pdo->prepare("SELECT * FROM scores ORDER BY score DESC, id DESC LIMIT 10");
	// $query->setFetchMode(PDO::FETCH_ASSOC);
	// $status = $query->execute();
	// return send_response(TRUE, $query->fetchAll());
}

function action_restricted()
{
	$user = basic_auth('test2');
	return $user;
	
}

// RANK
function action_rank()
{
	$score = arr($_GET, 'score', 0);
	
	$pdo = pdo_connect();
	
	$query = $pdo->prepare("SELECT COUNT(*) + 1 AS rank FROM scores WHERE score > :score");
	$query->execute(array(':score' => $score));
	$result = $query->fetchAll();
	return send_response(TRUE, $result[0]['rank']);
}

// ADD SCORE
function action_add()
{
	$values = array(
		':uid' 				=> arr($_GET, 'uid'),
		':score' 			=> arr($_GET, 'score'),
		':name' 			=> arr($_GET, 'name'),
		':updated_at'		=> date('Y-m-d H:i:s'),
		':created_at'		=> date('Y-m-d H:i:s'),
	);
	
	// validate
	foreach ($values as $field => $value)
	{
		if (empty($value)) return send_response(FALSE, 'Field missing: '.$field);
	}
	
	if (sha1($values[':uid'].$values[':score'].$values[':name'].'avonreps') != arr($_GET, 'hash'))
	{
		return send_response(FALSE, 'Bad hash, man');
	}
	
	// save
	$pdo = pdo_connect();
	
	$query = $pdo->prepare(
		"INSERT INTO scores (uid, score, name, updated_at, created_at)
		VALUES (:uid, :score, :name, :updated_at, :created_at)"
	);
	
	$count = $query->execute($values);
	
	if ($count == 1) return send_response(TRUE, 'Score added.');
	else return send_response(FALSE, 'Problem with query.');
}

########################################
run($settings);
########################################
