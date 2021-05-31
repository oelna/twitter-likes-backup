<?php

	error_reporting(E_ALL);
	ini_set('display_errors', '1');

	set_time_limit(180);

	define('DS', DIRECTORY_SEPARATOR);
	define('TWEETS', __DIR__.DS.'tweets');
	define('STATUS_FILE', __DIR__.DS.'status.json');

	$config = [
		'items_per_page' => 8
	];

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

	$last_update = 0;
	if(file_exists(STATUS_FILE)) {
		$status_json = json_decode(file_get_contents(STATUS_FILE), true);
		$last_update = $status_json['last_update'];
	}

?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>Twitter Archive</title>
	<style>
		:root {
			--highlight: #ff7919;
		}
		* { box-sizing: border-box; margin: 0; padding: 0; }
		html {
			font: 100%/1.4 -system-ui, sans-serif;
			background-color: #ddd;
		}

		body { padding: 1em; }

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
			gap: 0.2em;
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

		form input[type="submit"]:hover,
		form input[type="submit"]:focus,
		button:hover,
		button:focus {
			background: var(--highlight);
		}

		.pagination {
			margin: 2em 0;
		}

		.pagination ul {
			width: 100%;
			display: flex;
			flex-wrap: wrap;
			list-style: none;
			gap: 0.2em;
		}

		.pagination ul li {
			flex: 1;
			text-align: center;
		}

		.pagination ul a {
			display: block;
			width: 100%;
			text-decoration: none;
			background: #333;
			color: #eee;
			padding: 0.2em 0.2em;
		}

		.pagination ul .current a,
		.pagination ul a:hover,
		.pagination ul a:focus {
			background: var(--highlight);
			color: #eee;
		}

		.pagination ul .arrow.disabled a {
			opacity: 0.2;
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

		.tweets li .text .media {
			display: block;
			margin-top: 1em;
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
			$page = (!empty($_GET['page'])) ? (int) $_GET['page'] : 1;

			// count all
			$files = array_diff(scandir(TWEETS), array('.', '..'));

			if(!empty($_GET['s'])) {
				// find tweets matching the search term
				$tweets_unfiltered = find($_GET['s']);
			} else {
				// use all tweets
				$tweets_unfiltered = array_map(function($file) {
					return pathinfo($file, PATHINFO_FILENAME);
				}, $files);

				// approximate sorting by date by using the tweet id
				natsort($tweets_unfiltered);
			}

			// latest first
			$tweets_unfiltered = array_reverse($tweets_unfiltered);

			$tweets = array_slice($tweets_unfiltered, ($page-1)*$config['items_per_page'], $config['items_per_page']);

			// calculate some stats
			$pages_total = ceil(count($tweets_unfiltered)/$config['items_per_page']);
			if($page > $pages_total) { $page = $pages_total; }
			if($page < 1) { $page = 1; }

			// build a basic pagination
			$range = range(1, $pages_total);
			$pagination_pages = [];
			foreach($range as $p) {
				if($p <= 2) {
					$pagination_pages[] = $p;
				} elseif($p > ($pages_total-2)) {
					$pagination_pages[] = $p;
				} elseif($p >= $page-3 && $p <= $page+3) {
					$pagination_pages[] = $p;
				}
			}

			// buffer the pagination markup so we can output it twice
			ob_start();
			echo('<nav class="pagination"><ul>');
			echo('<li class="arrow prev '.($page==1 ? 'disabled': '').'"><a href="?'.(!empty($_GET['s']) ? 's='.$_GET['s'].'&amp;' : '').'page='.max(1,$page-1).'">Prev</a></li>');
			$i = 1;
			foreach ($pagination_pages as $p) {
				if($i > 1 && $i+1 !== $p) {
					echo('<li class="split">…</li>');
				}
				$current = ($p == $page) ? 'current' : '';
				echo('<li class="'.$current.'"><a href="?'.(!empty($_GET['s']) ? 's='.$_GET['s'].'&amp;' : '').'page='.$p.'">'.$p.'</a></li>');
				$i = $p;
			}
			echo('<li class="arrow next'.($page==$pages_total ? 'disabled': '').'"><a href="?'.(!empty($_GET['s']) ? 's='.$_GET['s'].'&amp;' : '').'page='.min($pages_total,$page+1).'">Next</a></li>');
			echo('</ul></nav>');

			$pagination = ob_get_contents();
			ob_end_clean();

		?><form action="" method="get">
			<button type="button" onclick="window.location='<?= strtok($_SERVER['REQUEST_URI'], '?') ?>'; return false;">Clear</button>
			<input type="search" name="s" placeholder="search term" value="<?= isset($_GET['s']) ? $_GET['s'] : '' ?>" /><br />
			<input type="submit" value="Search" />
		</form>

		<?= $pagination ?>
		
		<?php if(!empty($_GET['s'])): ?>
			<h2>Showing <?= count($tweets_unfiltered); ?> results in <?= count($files) ?> archived tweets (<?= $pages_total ?> pages)</h2>
		<?php else: ?>
			<h2>Showing all of <?= count($files) ?> archived tweets (<?= $pages_total ?> pages)</h2>
		<?php endif; ?>
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
				if(!empty($_GET['s'])) {
					$text = str_ireplace($_GET['s'], '<mark>'.$_GET['s'].'</mark>', $text);
				}

				// expand t.co urls
				if(!empty($tweet_data['entities']['urls'])) {
					foreach($tweet_data['entities']['urls'] as $url) {
						$link = '<a href="'.$url['expanded_url'].'">'.$url['display_url'].'</a>';
						$text = str_replace($url['url'], $link, $text);
					}
				}

				// embed media
				if(!empty($tweet_data['entities']['media'])) {
					foreach($tweet_data['entities']['media'] as $media) {
						if($media['type'] == 'photo') {
							$link = '<a class="media" href="'.$media['expanded_url'].'"><img src="'.$media['media_url_https'].'" loading="lazy" /></a>';
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

		<?= $pagination ?>

		<footer>
			<p>Last updated: <time class="last-crawl" datetime="<?= $last_update ?>"><?= $last_update > 0 ? $last_update : 'never' ?></time></p>
		</footer>
	</div>
</body>
</html>
