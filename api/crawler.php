<?php
function crawl($feedurl) {
	$dbh = $GLOBALS['dbh'];
	$time = time();
	$xml = simplexml_load_file($feedurl);

	$sth = $dbh->query("SELECT * FROM feed WHERE url='$feedurl'");
	if ($result = $sth->fetch(PDO::FETCH_ASSOC)) {
		$feedid = $result['FeedID'];
		
		$dbh->exec("UPDATE feed SET crawlts=$time");
	}
	else {
		$dbh->exec("INSERT INTO feed (url, crawlts) VALUES('$feedurl', $time)");
		$feedid = $dbh->lastInsertId();
	}
	
	next_child($xml->channel, "channel/", $feedid, $time);

	return $feedid;
}

function next_child($node, $url, $feedid, $time) {
	foreach ($node->children() as $child) {
		process_child($child, null, $url, $feedid, $time);
	}

	foreach ($node->getDocNamespaces() as $ns => $nsurl) {
		foreach ($node->children($nsurl) as $child) {
			process_child($child, $ns, $url, $feedid, $time);
		}
	}
}

function process_child($child, $ns, $url, $feedid, $time) {
	static $item = false;
	static $buffer = array();
	static $itemid = null;
	$dbh = $GLOBALS['dbh'];

	if ($ns != null) {
		$newurl = $url.$ns.":".$child->getName();
	}
	else {
		$newurl = $url.$child->getName();
	}

	if (!startsWith($url, "channel/item")) {
		$itemid = null;

		$sth = $dbh->query("SELECT * FROM feedcontent WHERE feedid=$feedid AND location='$newurl'");
		if ($sth && $sth->rowCount() > 0) {
			return;
		}
	}

	if ($child->count() > 0) {
		if ($child->getName() == "item") {
			$item = true;
		}
		next_child($child, $newurl."/", $feedid, $time);
	}
	else {
		if ($child->getName() == "guid") {
			$item = false;

			$sth = $dbh->query("SELECT * FROM feedcontent WHERE location='channel/item/guid' AND content='$child' AND feedid=$feedid");
			if ($sth && $sth->rowCount() > 0) {
				// Existing item
			}
			else {
				$dbh->exec("INSERT INTO itemid () VALUES()");
				$itemid = $dbh->lastInsertId();

				foreach ($buffer as $line) {
					push_line($feedid, $line["location"], $itemid, $line["content"], $time);
				}
				$buffer = array();
			}
		}

		if ($item) {
			array_push($buffer, array("location" => $newurl, "content" => (string)$child));

			foreach ($child->attributes() as $key => $value) {
				array_push($buffer, array("location" => "$newurl/$key", "content" => $value));
			}
		}
		else {
			if (!($itemid == null && startsWith($newurl, "channel/item"))) {
				push_line($feedid, $newurl, $itemid, (string)$child, $time);

				foreach ($child->attributes() as $key => $value) {
					push_line($feedid, "$newurl/$key", $itemid, $value, $time);
				}
			}
		}
	}
}

function push_line($feedid, $location, $itemid, $content, $time) {
	$sth = $GLOBALS['dbh']->prepare("INSERT INTO feedcontent (feedid, location, itemid, content, crawlts) VALUES($feedid, '$location', :itemid, '$content', $time)");
	$sth->bindParam(':itemid', $itemid, PDO::PARAM_INT);
	$sth->execute();
}

function crawler_get_cast($feedid) {
	$cast = array();

	$sth = $GLOBALS['dbh']->query("SELECT * FROM feedcontent WHERE feedid=$feedid");
	if ($result = $sth->fetchAll()) {
		foreach ($result as $row) {
			if (!startsWith($row['Location'], "channel/item")) {
				$exploded = explode("/", $row['Location']);
				if (sizeof($exploded) > 2) {
					$cast[$exploded[1]][$exploded[2]] = $row['Content'];
				}
				else {
					$cast[$exploded[1]] = $row['Content'];
				}
			}
		}
	}

	return $cast;
}

function crawler_get_episodes($feedid) {
	$episodes = array();
	$itemid = null;	
	$previtemid = null;
	$i = -1;

	$sth = $GLOBALS['dbh']->query("SELECT * FROM feedcontent WHERE feedid=$feedid");
	if ($result = $sth->fetchAll()) {
		foreach ($result as $row) {
			$itemid = $row['ItemID'];
			if ($itemid != $previtemid) {
				$i++;
			}

			if (startsWith($row['Location'], "channel/item")) {
				$exploded = explode("/", $row['Location']);
				if (sizeof($exploded) > 3) {
					$episodes[$i][$exploded[2]][$exploded[3]] = $row['Content'];
				}
				else {
					if ($exploded[2] == "guid") {
						$episodes[$i]["guid"]["guid"] = $row['Content'];
					}
					else {
						$episodes[$i][$exploded[2]] = $row['Content'];
					}
				}
			}
			$previtemid = $itemid;
		}
	}

	return $episodes;
}
?>