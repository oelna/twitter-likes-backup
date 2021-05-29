<?php
	
	error_reporting(E_ALL);
	ini_set('display_errors', '1');

	set_time_limit(180);

	$eol = (php_sapi_name() === 'cli') ? "\n" : "<br />";
	define('EOL', $eol);
	define('DS', DIRECTORY_SEPARATOR);
	define('TWEETS', __DIR__.DS.'tweets');

	// handle CLI options
	$shortopts  = 'v';
	$longopts  = ['verbose'];
	$options = getopt($shortopts, $longopts);

	$config = [
		'screen_name' => 'yourscreenname',
		'count' => 100, // how many tweets to fetch on each request (max: 200)
		'url' => 'https://api.twitter.com/1.1/favorites/list.json',
		'max_iterations' => 2, // how many API requests to make in sequence
		'verbose' => false,

		'oauth_access_token' => '',
		'oauth_access_token_secret' => '',
		'consumer_key' => '',
		'consumer_secret' => ''
	];

	if(array_key_exists('v', $options) || array_key_exists('verbose', $options)) {
		$config['verbose'] = true;
	}

	$status = [
		'length' => -1,
		'start' => 0,
		'next' => -1,

		'current_iteration' => 0,

		'tweets' => [],

		'max_id' => -1,
		'total_tweets_received' => 0,
		'total_tweets_imported' => 0,
		'earliest_date' => time(),
		'latest_date' => 0
	];

	echo2(EOL);

	if(isset($_GET['reset'])) {

		unlink(__DIR__.DS.'max_id.txt');

		array_map('unlink', glob(TWEETS."/*.json"));
		rmdir(TWEETS);

		echo2('reset the import progress. <a href="./backup.php">start over</a>'.EOL);
		exit();
	}

	if(!is_dir(TWEETS)) {
		mkdir(TWEETS);
	}

	if(file_exists(__DIR__.DS.'max_id.txt')) {
		$status['max_id'] = (int) file_get_contents(__DIR__.DS.'max_id.txt');
	}

	function echo2($str) {
		global $config;
		if($config['verbose']) {
			echo($str);
		}
	}

	function buildBaseString($baseURI, $method, $params) {
		$r = array();
		ksort($params);
		foreach($params as $key => $value){
			$r[] = "$key=" . rawurlencode($value);
		}
		return $method."&" . rawurlencode($baseURI) . '&' . rawurlencode(implode('&', $r));
	}

	function buildAuthorizationHeader($oauth) {
		$r = 'Authorization: OAuth ';
		$values = array();
		foreach($oauth as $key => $value)
			$values[] = "$key=\"" . rawurlencode($value) . "\"";
		$r .= implode(', ', $values);
		return $r;
	}

	function makeRequest($url, $max_id = -1) {
		global $config;
		global $status;

		$curl_url = $url . '?screen_name='.ltrim($config['screen_name'], '@').'&count='.$config['count'];

		$oauth = array(
			'screen_name' => $config['screen_name'],
			'count' => $config['count'],
			'oauth_consumer_key' => $config['consumer_key'],
			'oauth_nonce' => time(),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_token' => $config['oauth_access_token'],
			'oauth_timestamp' => time(),
			'oauth_version' => '1.0'
		);

		if($max_id > -1) {
			$oauth['max_id'] = $max_id;
			$curl_url .= '&max_id='.$max_id;
		}

		$base_info = buildBaseString($url, 'GET', $oauth);
		$composite_key = rawurlencode($config['consumer_secret']) . '&' . rawurlencode($config['oauth_access_token_secret']);
		$oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
		$oauth['oauth_signature'] = $oauth_signature;

		// Make requests
		$header = array(buildAuthorizationHeader($oauth), 'Expect:');
		$options = array(
			CURLOPT_HTTPHEADER => $header,
			CURLOPT_HEADER => false,
			CURLOPT_URL => $curl_url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false
		);

		$feed = curl_init();
		curl_setopt_array($feed, $options);
		$json = curl_exec($feed);

		$header_data = curl_getinfo($feed);

		if ($header_data['http_code'] == 429) {
			echo2('HTTP 427: Exceeded request limit (75 per 15min)!');
			exit();
			// todo: output what we have?
		}

		curl_close($feed);

		return $json;
	}

	function loadAll($url) {
		global $status;
		global $config;

		if ($status['length'] != 1 && $status['current_iteration'] < $config['max_iterations']) {
			if ($status['next'] != -1) {
				$status['max_id'] = $status['next'];
			}

			$json = makeRequest($url, $status['max_id']);
			$data = json_decode($json, true);

			$status['length'] = sizeof($data);
			$status['total_tweets_received'] += $status['length'];

			echo2('received '.$status['length'].' tweets'.EOL);
			echo2('starting at id '.$status['max_id'].EOL);
			if ($status['length'] > 0) { echo2('first date is '.$data[0]['created_at'].EOL); }
			if ($status['length'] > 0) { echo2('last date is '.$data[$status['length']-1]['created_at'].EOL); }

			$tweets_imported = 0;
			foreach($data as $tweet) {

				$filename = TWEETS.DS.$tweet['id_str'].'.json';
				if(!file_exists($filename)) {
					file_put_contents($filename, json_encode($tweet, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK));

					$time = strtotime($tweet['created_at']);
					touch($filename, $time);

					if($time < $status['earliest_date']) {
						$status['earliest_date'] = $time;
					}
					if($time > $status['latest_date']) {
						$status['latest_date'] = $time;
					}

					$tweets_imported += 1;
					$status['total_tweets_imported'] += 1;
				}
			}

			echo2($tweets_imported.' tweets imported this run'.EOL.EOL);

			$status['tweets'] = array_merge($status['tweets'], $data);

			$status['next'] = $data[$status['length'] - 1]['id_str'];
			
			$status['start'] += $status['length'] - 1;

			$status['current_iteration'] += 1;
			file_put_contents(__DIR__.DS.'max_id.txt', $status['next']);
			loadAll($url);
		} else {
			
			echo2($status['total_tweets_received'].' tweets received in total'.EOL);
			echo2($status['total_tweets_imported'].' tweets imported in total'.EOL);
			if($status['total_tweets_received'] <= 1) {
				// reached the last tweet? invalidate max_id limit
				$status['max_id'] = -1;
				file_put_contents(__DIR__.DS.'max_id.txt', $status['max_id']);
				echo2('reached the beginning of time. reset the max_id.'.EOL);
			} else {
				echo2('earliest tweet imported: '.date('Y-m-d H:i:s', $status['earliest_date']).EOL);
				echo2('latest tweet imported: '.date('Y-m-d H:i:s', $status['latest_date']).EOL);
			}
		}
	}

	loadAll($config['url']);
