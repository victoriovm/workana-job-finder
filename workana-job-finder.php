<?php

declare(strict_types=1);

define("COLOR_GREEN", "\033[0;32m");
define("COLOR_YELLOW", "\033[1;33m");
define("COLOR_RESET", "\033[0m");

$config = json_decode(file_get_contents(__DIR__ . "/config.json"), true);

function fetch(int $page, string $category): array {
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, "https://www.workana.com/jobs?category=" . $category . "&has_few_bids=1&language=pt&page=" . $page);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		"Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7",
		"User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Mobile Safari/537.36",
		"X-Requested-With: XMLHttpRequest"
	]);

	$result = curl_exec($ch);

	if (curl_errno($ch)) {
		throw new ErrorException(curl_error($ch));
	}
	curl_close($ch);

	return json_decode($result, true)["results"]["results"];
}

$jobs = [];
$finded = [];

if ($config["ignore-finded"] && file_exists(__DIR__ . "/jobs.json")) {
	$finded = json_decode(file_get_contents(__DIR__ . "/jobs.json"), true);
}

echo PHP_EOL;

for ($page = 0; $page < $config["pages"]; $page++) {
	foreach (fetch($page, $config["category"]) as $job) {
		$bids = (int) str_replace("Propostas: ", "", $job["totalBids"]);

		if ($bids <= $config["max-bids"]) {
			$title = strip_tags($job["title"]);
			$description = html_entity_decode(strip_tags($job["description"]), ENT_QUOTES | ENT_HTML5, "UTF-8");

			$index = $title . $description;

			if (!in_array($index, $jobs, true) && (!$config["ignore-finded"] || !in_array($index, $finded, true))) {
				$jobs[] = $index;
				$finded[] = $index;

				echo "---------------------------------------------------------" . PHP_EOL;
				echo "Título: " . COLOR_YELLOW . $title . PHP_EOL . COLOR_RESET;
				echo "Descrição: " . COLOR_YELLOW . substr($description, 0, $config["description-length"]) . "..." . PHP_EOL . COLOR_RESET;
				echo "Link: " . COLOR_YELLOW . $job["dismissInvitationUrl"] . PHP_EOL . COLOR_RESET;
				echo "Postado: " . COLOR_YELLOW . $job["postedDate"] . PHP_EOL . COLOR_RESET;
				echo "---------------------------------------------------------" . PHP_EOL;
				echo PHP_EOL;
			}
		}
	}
}

if ($config["ignore-finded"]) {
	file_put_contents(__DIR__ . "/jobs.json", json_encode($finded, JSON_PRETTY_PRINT));
}
echo "Total Encontrado: " . COLOR_GREEN . count($jobs) . PHP_EOL . COLOR_RESET;