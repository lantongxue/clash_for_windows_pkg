<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
print_r(getenv());
echo getenv('GITHUB_TOKEN').PHP_EOL;
$client = new Client([
    'base_uri' => 'https://api.github.com',
    'headers' => [
        'Authorization' => 'Bearer '.getenv('GITHUB_TOKEN'),
        'Accept' => 'application/vnd.github+json',
        'X-GitHub-Api-Version' => '2022-11-28'
    ]
]);

$latest = $client->get('repos/Fndroid/clash_for_windows_pkg/releases/latest');
$latest = json_decode($latest->getBody()->getContents(), true);

try {
    $own_repo = $client->get('repos/lantongxue/clash_for_windows_pkg/releases/tags/'.$latest['tag_name']);
    $own_repo = json_decode($own_repo->getBody()->getContents(), true);

    if($own_repo['tag_name'] === $latest['tag_name']) {
        echo $latest['tag_name'].' this version was published';exit;
    }
    
} catch (ClientException $exception) {
    echo $exception->getResponse()->getBody()->getContents();
}

$release = $client->post('repos/lantongxue/clash_for_windows_pkg/releases', [
    'json' => [
        'tag_name' => $latest['tag_name'],
        'name' => $latest['name'],
        'body' => $latest['body'],
        'draft' => false,
        'prerelease' => false,
        'make_latest' => 'true',
    ]
]);

$release = json_decode($release->getBody()->getContents(), true);

foreach($latest['assets'] as $asset) {
    if(stripos($asset['name'], '-x64-linux.tar.gz') === false) {
        continue;
    }
    $base_name = str_replace('.tar.gz', '', $asset['name']);
    system('wget '.$asset['browser_download_url']);
    system('tar -zxvf '.$asset['name'].' -C clash_for_windows/opt/clash_for_windows'.' "Clash for Windows-'.$latest['tag_name'].'-x64-linux/"'. ' --strip-components 1');

    $deb_name = $base_name.'.deb';
    system('dpkg-deb -b clash_for_windows/'.' '.$deb_name);

    $client->post('https://uploads.github.com/repos/lantongxue/clash_for_windows_pkg/releases/'.$release['id'].'/assets', [
        'headers' => [
            'Content-Type' => 'application/octet-stream',
        ],
        'query' => [
            'name' => $deb_name,
        ],
        'body' => fopen($deb_name, 'r'),
    ]);
}

echo 'ok'.PHP_EOL;
