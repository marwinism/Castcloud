<?php
require 'Slim/Slim.php';
\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();
GLOBAL $app;

include 'cc-settings.php';
GLOBAL $dbh;

include 'authmiddleware.php';
include 'util.php';

$app->add(new AuthMiddleware());

$app->group('/account', function() use($app) {

	$app->post('/login', function() use($app) {
		$username = $app->request->params('username');
		$password = $app->request->params('password');
		$clientname = $app->request->params('clientname');
		$clientdescription = $app->request->params('clientdescription');
		$clientversion = $app->request->params('clientversion');
		$uuid = $app->request->params('uuid');
		$apikey = $app->request->params('apikey');

		$dbh = $GLOBALS['dbh'];
		$sth = $dbh->query("SELECT * FROM users WHERE username='$username'");
		if ($sth) {
			if ($result = $sth->fetch(PDO::FETCH_ASSOC)) {
				$userid = $result['UserID'];
				$salt = $result['Salt'];
				if ($result['Password'] == md5($password.$salt)) {
					$sth = $dbh->query("SELECT * FROM client WHERE Name='$clientname'");
					if ($result = $sth->fetch(PDO::FETCH_ASSOC)) {
						$sth = $dbh->query("SELECT * FROM clientauthorization WHERE userid='$userid'");
                                        	if ($result = $sth->fetch(PDO::FETCH_ASSOC)) {
                                                	$token = $result['Tolken'];
                                        	}
                                        }
					else {
						$token = base64_encode(random_bytes(32));
						// shit hits the fan
/*						$dbh->exec("INSERT INTO client (name) VALUES('$clientname')");
						$sth = $dbh->query("SELECT LAST_INSERT_ID();");
						if ($result = $sth->fetch(PDO::FETCH_ASSOC)) {
							$dbh->exec("INSERT INTO clientauthorization (userid, clientid, tolken, clientdescription, clientversion, uuid, seents) VALUES($userid, 1, '$token', 'Castcloud', '1.0', '', 1881)");
						}*/
					}

					json(array("token" => $token));
				}
				else {
					json(array("status" => "Login failed"));
				}
			}
			else {
				json(array("status" => "Login failed"));
			}
		}
		else {
			error_log("Castcloud database error: " . $dbh->errorInfo()[2], 0);
			json(array("status" => "Database fail"));
		}
	});

	$app->get('/ping', function() use($app) {
		json(array("status" => "Logged in"));
	});

	$app->get('/settings', function() use($app) {
		json(array("key" => "value", "key2" => "value"));
	});

	$app->post('/settings', function() use($app) {
		json(array("status" => "success"));
	});

	$app->get('/takeout', function() use($app) {
		json(array("Not" => "Implemented"));
	});

	$app->get('/takeout/opml', function() use($app) {
		json(array("Not" => "Implemented"));
	});

	$app->post('/takeout/opml', function() use($app) {
		json(array("Not" => "Implemented"));
	});

});

$app->group('/library', function() use($app) {

	$app->get('/newepisodes', function() use($app) {
		json(array("Not" => "Implemented"));
	});

	$app->get('/episodes/:castid', function($castid) use ($app) {
		json(array("Not" => "Implemented"));
	});

	$app->get('/casts', function() use ($app) {
		json(array("Not" => "Implemented"));
	});

	$app->post('/casts', function() use ($app) {
		$feedurl = $app->request->params('feedurl');

		json(array("status" => "success"));
	});

	$app->get('/casts/:tag', function($tag) use ($app) {
		json(array("Not" => "Implemented"));
	});

	$app->get('/events', function() use ($app) {
		json(array("Not" => "Implemented"));
	});

	$app->post('/events', function() use ($app) {
		json(array("Not" => "Implemented"));
	});

	$app->get('tags', function() use ($app) {
		json(array("Not" => "Implemented"));
	});

});

$app->run();

/**
 * Eksempel som kompilerer:
 *
 * @SWG\Resource(
 *		basePath="http://localhost/api",
 *      resourcePath="/login",
 *      @SWG\Api(
 *          path="/login",
 *          @SWG\Operation(
 *              nickname="test",
 *              method="GET",
 *              summary="This is a test",
 *				type="Herp"
 *          )
 *      )
 * )
 */
?>
