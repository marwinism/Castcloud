<?php
require '../lib/Slim/Slim.php';
\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();
GLOBAL $app;

include 'cc-settings.php';
GLOBAL $db_prefix,$dbh;

include 'authmiddleware.php';
include 'util.php';
include 'crawler.php';
include 'login.php';
include 'db.php';

$app->db = new DB($dbh);

$app -> add(new AuthMiddleware());

/**
 * @SWG\Resource(
 *   apiVersion="1.0.0",
 *   swaggerVersion="1.2",
 *   basePath="http://api.castcloud.org/api",
 *   resourcePath="/account",
 *   description="Account related operations",
 *   produces="['application/json']"
 * )
 */
$app -> group('/account', function() use ($app) {
	$app -> post('/login', function() use ($app) {
		post_login($app);
	});

	/**
	 * @SWG\Api(
	 * 	path="/account/ping",
	 * 	description="Tests if token works",
	 * 	@SWG\Operation(
	 * 		method="GET",
	 * 		nickname="Ping",
	 * 		summary="Test token",
	 * 		type="Herp",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> get('/ping', function() use ($app) {
		json(array("status" => "Logged in"));
	});

	/**
	 * @SWG\Api(
	 * 	path="/account/settings",
	 * 	description="Settings",
	 * 	@SWG\Operation(
	 * 		method="GET",
	 * 		nickname="Get Settings",
	 * 		summary="Get Settings",
	 * 		type="Herp",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> get('/settings', function() use ($app) {
		
		$db_prefix = $GLOBALS['db_prefix'];
		$settings = array();

		$sth = $GLOBALS['dbh']->query("SELECT * FROM {$db_prefix}setting WHERE userid=$app->userid");
		if ($sth) {
			foreach ($sth as $row) {
				$settings[$row['Setting']] = $row['Value'];
			}
		}

		json($settings);
	});

	/**
	 * @SWG\Api(
	 * 	path="/account/settings",
	 * 	description="Settings",
	 * 	@SWG\Operation(
	 * 		method="POST",
	 * 		nickname="Set Settings",
	 * 		summary="Set Settings",
	 * 		type="Herp",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="json",
	 * 			description="New or modified settings (TBD)",
	 * 			paramType="body",
	 * 			required=true,
	 * 			type="string"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> post('/settings', function() use ($app) {
		if ($app->request->params('json') == null) {
			$settings = $app->request->params();
		}
		else {			
			$settings = json_decode($app->request->params('json'));
		}

		$dbh = $GLOBALS['dbh'];
		$db_prefix = $GLOBALS['db_prefix'];
		foreach($settings as $key => $value) {
			$sth = $dbh->query("SELECT * FROM {$db_prefix}setting WHERE userid=$app->userid AND setting='$key'");
			if ($sth && $sth->rowCount() > 0) {
				$dbh->exec("UPDATE {$db_prefix}setting SET value='$value' WHERE userid=$app->userid AND setting='$key'");				
			}
			else {
				$dbh->exec("INSERT INTO {$db_prefix}setting (userid, setting, value) VALUES($app->userid, '$key', '$value')");
			}
		}

		json(array("status" => "success"));
	});

	$app -> get('/takeout', function() use ($app) {
		json(array("Not" => "Implemented"));
	});

});

/**
 * @SWG\Resource(
 *   apiVersion="1.0.0",
 *   swaggerVersion="1.2",
 *   basePath="http://api.castcloud.org/api",
 *   resourcePath="/library",
 *   description="Library related operations",
 *   produces="['application/json']"
 * )
 */
$app -> group('/library', function() use ($app) {

	/**
	 * @SWG\Api(
	 * 	path="/library/newepisodes",
	 * 	description="Get new episodes",
	 * 	@SWG\Operation(
	 * 		method="GET",
	 * 		nickname="Get new episodes",
	 * 		summary="Get new episodes",
     * 		type="newepisodesresult",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="since",
	 * 			description="timestamp of last call",
	 * 			paramType="query",
	 * 			required=false,
	 * 			type="integer"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> get('/newepisodes', function() use ($app) {
		include_once 'models/newepisodesresult.php';
		json(new newepisodesresult($app->db->get_new_episodes($app->request->params('since'))));
	});

	/**
	 * @SWG\Api(
	 * 	path="/library/episodes/{castid}",
	 * 	description="Get all episodes of a cast",
	 * 	@SWG\Operation(
	 * 		method="GET",
	 * 		nickname="Get all episodes",
	 * 		summary="Get all episodes",
     * 		type="array",
     * 		items="$ref:episode",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="castid",
	 * 			description="The casts castid",
	 * 			paramType="path",
	 * 			required=true,
	 * 			type="integer"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> get('/episodes/:castid', function($castid) use ($app) {
		json($app->db->get_episodes($castid));
	});

	/**
	 * @SWG\Api(
	 * 	path="/library/casts",
	 * 	description="Get users subcriptions",
	 * 	@SWG\Operation(
	 * 		method="GET",
	 * 		nickname="Get users subcriptions",
	 * 		summary="Get users subcriptions",
	 * 		type="Herp",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> get('/casts', function() use ($app) {
		json($app->db->get_casts());
	});

	$app->get('/casts.opml', function() use($app) {
		json(array("Not" => "Implemented"));
	});
	
	$app -> post('/casts.opml', function() use ($app) {
		json(array("Not" => "Implemented"));
	});

	/**
	 * @SWG\Api(
	 * 	path="/library/casts",
	 * 	description="Get users subcriptions",
	 * 	@SWG\Operation(
	 * 		method="POST",
	 * 		nickname="Get users subcriptions",
	 * 		summary="Get users subcriptions",
	 * 		type="Herp",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="feedurl",
	 * 			description="URL of podcast feed",
	 * 			paramType="form",
	 * 			required=true,
	 * 			type="string"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> post('/casts', function() use ($app) {
		$feedurl = $app -> request -> params('feedurl');
		$feedid = crawl($feedurl);
		$userid = $app -> userid;

		$dbh = $GLOBALS['dbh'];
		$db_prefix = $GLOBALS['db_prefix'];
		$sth = $dbh -> query("SELECT * FROM {$db_prefix}subscription WHERE feedid=$feedid AND userid=$userid");
		if ($sth && $sth -> rowCount() < 1) {
			$dbh -> exec("INSERT INTO {$db_prefix}subscription (feedid, tags, userid) VALUES($feedid, 'bjarne,nils', $userid)");
		}

		json(array("status" => "success"));
	});

	/**
	 * @SWG\Api(
	 * 	path="/library/casts/{id}",
	 * 	description="Unsubscribe from a cast",
	 * 	@SWG\Operation(
	 * 		method="DELETE",
	 * 		nickname="Unsubscribe from a cast",
	 * 		summary="Unsubscribe from a cast",
     * 		type="array",
     * 		items="$ref:episode",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="id",
	 * 			description="The casts id",
	 * 			paramType="path",
	 * 			required=true,
	 * 			type="integer"
	 * 		)
	 * 	)
	 * )
	 */
	$app->delete('/casts/:id', function($id) use($app) {
		$userid = $app->userid;
		$db_prefix = $GLOBALS['db_prefix'];
		$GLOBALS['dbh']->exec("DELETE FROM {$db_prefix}subscription WHERE feedid=$id AND userid=$userid");
	});

	/**
	 * @SWG\Api(
	 * 	path="/library/casts/{tag}",
	 * 	description="Get users subcriptions for spesific tag",
	 * 	@SWG\Operation(
	 * 		method="GET",
	 * 		nickname="Get users subcriptions for spesific tag",
	 * 		summary="Get users subcriptions for spesific tag",
	 * 		type="Herp",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="tag",
	 * 			description="filter by tag",
	 * 			paramType="path",
	 * 			required=false,
	 * 			type="string"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> get('/casts/tagged/:tag', function($tag) use ($app) {
		json($app->db->get_casts($tag));
	});

	/**
	 * @SWG\Api(
	 * 	path="/library/events",
	 * 	description="Get events",
	 * 	@SWG\Operation(
	 * 		method="GET",
	 * 		nickname="Get users tags",
	 * 		summary="Get users tags",
     * 		type="array",
     * 		items="$ref:event",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="since",
	 * 			description="timestamp of last call",
	 * 			paramType="query",
	 * 			required=false,
	 * 			type="integer"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="ItemID",
	 * 			description="filter by ItemID",
	 * 			paramType="query",
	 * 			required=false,
	 * 			type="integer"
	 * 		)
	 * 	)
	 * )
	 * 
	 * List of event types
		10 => "start",
		20 => "pause",
		30 => "unpause",
		40 => "slumber start",
		50 => "slumber end",
		60 => "seek start",
		70 => "seek end",
		80 => "end of track",
		90 => "deleted"
	 */
	$app -> get('/events', function() use ($app) {
		include 'models/eventsresult.php';

		$itemid = $app->request->params('ItemID');
		$since = $app->request->params('since');

		json(new eventsresult($app->userid, $itemid, $since));
	});

	/**
	 * @SWG\Api(
	 * 	path="/library/events",
	 * 	description="Add events",
	 * 	@SWG\Operation(
	 * 		method="POST",
	 * 		nickname="Add events",
	 * 		summary="Add events",
	 * 		type="void",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="json",
	 * 			description="New events (TBD)",
	 * 			paramType="body",
	 * 			required=true,
	 * 			type="array",
	 * 			items="$ref:event"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> post('/events', function() use ($app) {
		$db_prefix = $GLOBALS['db_prefix'];
		$receivedts = time();	
				
		$event = json_decode($app->request->params('json'));
		foreach ($json as $event) {
			$GLOBALS['dbh']->exec("INSERT INTO {$db_prefix}event (userid, type, itemid, positionts, clientts, receivedts, uniqueclientid) VALUES($app->userid, $event->type, $event->itemid, $event->positionts, $event->clientts, $receivedts, $app->uniqueclientid)");
		}

		json(array("Status" => "Success"));
	});

	/**
	 * @SWG\Api(
	 * 	path="/library/tags",
	 * 	description="Get users tags",
	 * 	@SWG\Operation(
	 * 		method="GET",
	 * 		nickname="Get users tags",
	 * 		summary="Get users tags",
	 * 		type="Herp",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> get('/tags', function() use ($app) {
		$dbh = $GLOBALS['dbh'];
		$db_prefix = $GLOBALS['db_prefix'];
		$userid = $app -> userid;
		$tags = array();
		$sth = $dbh -> query("SELECT Tags FROM {$db_prefix}subscription WHERE UserID=$userid");
		if ($sth){
			foreach ($sth as $row){
				$tag = $row['Tags'];
				if ($tag != '' && !strpos($tag,',')){
					array_push($tags, $tag);
				}
				if (strpos($tag,',')){
					$tags = array_merge($tags, explode( ',', str_replace(' ','',$tag)));
				}
			}
		}
		asort($tags);	
		json(array_values(array_unique($tags)));
	});

});

$app -> run();
?>
