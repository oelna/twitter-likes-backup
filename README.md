# Backup your Twitter likes (used to be called favorites)

This is a dirty way to get an off-site archive of your twitter likes and favorites as single JSON files.

Put this on a webserver of your choice that runs PHP.

The script will create a subdirectory `tweets` that will house the JSON files for each archived tweet. Also a `max_id.txt`, which logs the tweet ID used as offset in the API. Leave it alone.

## Requirements

It's too bad you have to apply as a Twitter developer for this now. It used to be simple. Just get the tokens from this page: https://developer.twitter.com/apps

- `oauth_access_token`
- `oauth_access_token_secret`
- `consumer_key`
- `consumer_secret`

Enter the data into the [`$config` array](./import.php#L18-L29)

## Settings

Set the username and download amounts for each run. Shouldn't be so high that you run into the API limit (75 requests every 15 minutes?)
For debugging, verbose mode can be useful, but for use in `cron` it's probably better to have it off.

## Run

The `import.php` script can be run in the browser, eg. called via URL, but the preferred way is via CLI, eg. in `cron`
Depending on your like count, you can run it frequently or less so. I have mine at once every 6 hours.

When the script is run, it requests tweets from the API and iterates over the result, saving every tweet it does not already have in the `tweets` directory. Every run it fetches about `$config['count]` times `$config['max_iterations]` tweets, so it may take quite a while until it has all your likes. Just let it do its thing. When it reaches the end ("the beginning of time"?), it will start over, requesting tweets again. This is because you may have likes tweets in the meantime that have early dates and would not show up if you only requested the latest likes.

## Result

The result after a full cycle should be a directory ("folder") `tweets`, which contains your likes in JSON format, containing the exact API response. You can do anything with those; extract data and import into a database of your choosing, build a frontend to browse them, eg. in Javascript or PHP, use them in Apps, etc.

The files will be named after the tweet ID and, if possible, have a modification date of the tweet post timestamp. That can help looking up things. But in case the files are touched in any way, the mod date will be lost obviously.

## Browsing

I included an early alpha release of a frontend to browse the tweets, `index.php`. In its current state it only supports searching for a term and doesn't have any pagination support, so handle with care!

## Ending remarks

That's it. This is everything I can tell you.
