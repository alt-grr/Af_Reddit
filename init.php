<?php

class Af_Reddit extends Plugin {

	private $host;

	function about() {
		return array(1.0,
			"Improve content of Reddit RSS feeds",
			"kuc");
	}

	function api_version() {
		return 2;
	}

	function init($host) {
		$this->host = $host;
		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
		$host->add_hook($host::HOOK_SANITIZE, $this);
	}

	function hook_article_filter($article) {
		$owner_uid = $article["owner_uid"];

		if (strpos($article["link"], ".reddit.com/r/") !== FALSE) {
			if (strpos($article["plugin_data"], "reddit,$owner_uid:") === FALSE) {
				$doc = new DOMDocument();
				@$doc->loadHTML('<?xml encoding="UTF-8"?>' . $article["content"]);

				if ($doc) {
					$found = FALSE;
					$xpath = new DOMXPath($doc);
					$tables = $xpath->query('//table');

					if ($tables) {
						$links = $xpath->query('//a[contains(.,"[link]")]');
						$target_page = $links->item(0)->getAttribute("href");

						if (strpos($target_page, "#") !== FALSE) {
							$target_page_parts = explode('#', $target_page);
							$target_page = $target_page_parts[0];
						}

						if (strpos($target_page, "://i.imgur.com/") !== FALSE) { // imgur single image

							// Fixes broken links like https://i.imgur.com/kD6Ay9h
							if (!strpos($target_page, '.jpg') && !strpos($target_page, '.png')
									&& !strpos($target_page, '.gif')) {

								$target_page .= '.jpg';
							}

							$article["content"] = '<a href="' . $target_page
								. '" target="_blank"><img src="' . $target_page . '"></a>';
							$found = TRUE;
						} else if (strpos($target_page, "imgur.com/a/") !== FALSE) { // imgur album

							$article["content"] = '<iframe class="imgur-album" width="100%" height="550" frameborder="0" src="'
								. $target_page . '/embed"></iframe>';
							$found = TRUE;
						} else if (preg_match("/^http(s)?:\/\/(m\.)?imgur.com\/([a-zA-Z0-9]+)(\/)?$/",
								$target_page, $matches)) { // imgur single page

							$article["content"] = '<a href="' . $target_page
								. '" target="_blank"><img src="https://i.imgur.com/' . $matches[3]
								. '.jpg"></a>';
							$found = TRUE;
						} else if (preg_match("/\.(jp(e)?g|png|gif)$/", $target_page)) { // images from other pages

							$article["content"] = '<a href="' . $target_page . '" target="_blank"><img src="' . $target_page . '"></a>';
							$found = TRUE;
						} else if (strpos($target_page, "://www.livememe.com/") !== FALSE) {
							$new_target_page = str_replace("://www.livememe.com/", "://i.lvme.me/", $target_page);
							$article["content"] = '<a href="' . $target_page . '" target="_blank"><img src="' . $new_target_page . '.jpg"></a>';
							$found = TRUE;
						}

						if ($found) {
							$article["plugin_data"] = "reddit,$owner_uid:" . $article["plugin_data"];
						}
					}
				}
			} else if (isset($article["stored"]["content"])) {
				$article["content"] = $article["stored"]["content"];
			}
		}

		return $article;
	}

	function hook_sanitize($doc, $site_url, $allowed_elements = null, $disallowed_attributes = null) {
		$xpath = new DOMXPath($doc);

		// remove sandbox from Imgur iframes
		$entries = $xpath->query('//iframe');
		foreach ($entries as $entry) {
			$src = $entry->getAttribute('src');
			if (strpos($src, "://imgur.com/a/") !== FALSE) {
				$entry->removeAttribute('sandbox');
			}
		}

		return $doc;
	}
}
