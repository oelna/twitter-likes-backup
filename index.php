<?php

	error_reporting(E_ALL);
    ini_set('display_errors', '1');

    set_time_limit(180);

    define('DS', DIRECTORY_SEPARATOR);
    define('TWEETS', __DIR__.DS.'tweets');

	function find($str) {
		$result = shell_exec('grep -l --ignore-case --fixed-strings "'.$str.'" ./tweets/*.json');
		$files = explode("\n", trim($result));
		$tweets = [];
		if(!empty($result) && !empty($files)) {
			foreach($files as $file) {
				$id = pathinfo($file, PATHINFO_FILENAME);
				$tweets[] = (string) $id;
			}
		}
		$tweets = array_unique($tweets);
		natsort($tweets); // dirty sorting by date
		$tweets = array_reverse($tweets);

		return $tweets;
	}

?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>Twitter Archive</title>
	<style>
		* { box-sizing: border-box; margin: 0; padding: 0; }
		html {
			font: 100%/1.4 -system-ui, sans-serif;
			background-color: #ddd;
		}

		a {
			color: #007aff;
			text-decoration: none;
		}

		.box {
			margin: 2rem auto;
			max-width: 80ch;
		}

		form {
			margin-bottom: 2em;
			display: flex;
		}

		form input[type="search"] {
			width: 100%;
			font-size: 100%;
			padding: 0.2em 0.5em;
		}

		form input[type="submit"],
		button {
			font-size: 100%;
			padding: 0.2em 0.5em;
			background: #333;
			color: #eee;
			border: 0;
		}

		.tweets li {
			list-style: none;
			margin-bottom: 2em;
			background-color: #fff;
			padding: 0.5em;
		}

		.tweets li .meta {
			padding-bottom: 0.5em;
			border-bottom: 1px solid #ddd;
			margin-bottom: 0.5em;
		}

		.tweets li .meta .time {
			color: #bbb;
			text-decoration: none;
		}

		.tweets li mark {
			background: orange;
		}

		.tweets li .text img {
			display: block;
			max-width: 100%;
			object-fit: contain;
			width: 100%;
			max-height: 16rem;
			height: 50vh;
			background-color: #f4f4f4;
		}
	</style>
</head>
<body>
	<div class="box">
		<?php
			$tweets = [];
			if(!empty($_GET['s'])) {
				$tweets = find($_GET['s']);
			}
		?><form action="" method="get">
			<input type="search" name="s" placeholder="search term" value="<?= isset($_GET['s']) ? $_GET['s'] : '' ?>" /><br />
			<input type="submit" value="Search" />
		</form>
		
		<h2><?= count($tweets); ?> results in <?= count(array_slice(scandir(TWEETS),2)) ?> archived tweets</h2>
		<ul class="tweets">
			<?php foreach($tweets as $tweet): ?>
			<?php
				$tweet_json = file_get_contents(TWEETS.DS.$tweet.'.json');
				$tweet_data = json_decode($tweet_json, true);
				$tweet_date = strtotime($tweet_data['created_at']);
			?><li data-id="<?= $tweet ?>">
				<div class="meta">
					<a class="time" href="https://twitter.com/<?= $tweet_data['user']['screen_name'] ?>/status/<?= $tweet_data['id_str'] ?>"><time datetime="<?= date('Y-m-d H:i:s', $tweet_date); ?>"><?= date('Y-m-d H:i:s', $tweet_date); ?></time></a>
					<div class="author">
						<span class="fullname"><?= $tweet_data['user']['name'] ?></span> 
						<a class="username" href="https://twitter.com/<?= $tweet_data['user']['screen_name'] ?>">@<?= $tweet_data['user']['screen_name'] ?></a>
					</div>
				</div>
				<p class="text"><?php

				// prepare tweet text
				$text = $tweet_data['text'];

				// highlight search term, todo: this currently matches URLs!
				$text = str_ireplace($_GET['s'], '<mark>'.$_GET['s'].'</mark>', $text);

				if(!empty($tweet_data['entities']['urls'])) {
					foreach($tweet_data['entities']['urls'] as $url) {
						$link = '<a href="'.$url['expanded_url'].'">'.$url['display_url'].'</a>';
						$text = str_replace($url['url'], $link, $text);
					}
				}
				if(!empty($tweet_data['entities']['media'])) {
					foreach($tweet_data['entities']['media'] as $media) {
						if($media['type'] == 'photo') {
							$link = '<a href="'.$media['expanded_url'].'"><img src="'.$media['media_url_https'].'" loading="lazy" /></a>';
							$text = str_replace($media['url'], $link, $text);
						}

						if(!empty($tweet_data['extended_entities']['media'])) {
							// this probably has video as well!
						}
					}
				}
				if(strpos($text, '://t.co/') !== false) {
					$link = '<a href="$1">$1</a>';
					$text = preg_replace('/(https?:\/\/t\.co\/[0-9a-zA-Z]+)/i', $link, $text);
				}

				$text = nl2br($text);

				echo($text);

			?></p></li>
			<?php endforeach; ?>
		</ul>
	</div>
</body>
</html>