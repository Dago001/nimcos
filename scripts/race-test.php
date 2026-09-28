<?php

/*
 * NIMCOS E-VOTING - double-vote race test (development / staging only).
 *
 * Signs in a demo voter, starts a ballot, then fires N identical final-ballot
 * submissions IN PARALLEL. Exactly one ballot must be recorded; every other
 * request must receive the same receipt (idempotent) or be refused.
 *
 * Usage (demo data loaded, NIMCOS_DEMO_MODE=true, server running):
 *   php scripts/race-test.php http://127.0.0.1:8000 90007 20 [extra,base,urls]
 * Submissions are spread across the base URL plus any extra comma-separated URLs
 * (e.g. several PHP-FPM / artisan-serve workers sharing the same database sessions).
 * Then check:  SELECT COUNT(*) FROM ballots;  (must increase by exactly 1)
 */

[$script, $base, $serviceNumber, $parallel, $extra] = $argv + [null, 'http://127.0.0.1:8000', '90007', 20, ''];
$targets = array_values(array_filter(array_merge([$base], explode(',', (string) $extra))));
$jar = tempnam(sys_get_temp_dir(), 'race');

function http(string $method, string $url, array $data = []): array
{
    global $jar;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $body = (string) curl_exec($ch);
    $info = curl_getinfo($ch);
    unset($ch); // flushes the cookie jar

    return [$body, $info];
}

function csrf(string $html): string
{
    preg_match('/name="_token" value="([^"]+)"/', $html, $m);

    return $m[1] ?? '';
}

[$h] = http('GET', "$base/");
http('POST', "$base/access", ['_token' => csrf($h), 'service_number' => $serviceNumber]);
[$h] = http('GET', "$base/verify");
if (! preg_match('/class="mono">(\d{6})</', $h, $m)) {
    exit("Could not read the demo OTP. Is NIMCOS_DEMO_MODE=true and is {$serviceNumber} eligible and not yet voted?\n");
}
http('POST', "$base/verify", ['_token' => csrf($h), 'code' => $m[1]]);
[$h, $i] = http('GET', "$base/elections");
[$h] = http('GET', $i['redirect_url'] ?: "$base/elections");
preg_match('/action="([^"]+\/start)"/', $h, $m);
http('POST', html_entity_decode($m[1]), ['_token' => csrf($h)]);
[$h] = http('GET', "$base/ballot");
preg_match_all('/name="selections\[([0-9a-f-]+)\](?:\[\])?"\s+value="([0-9a-f-]+)"/', $h, $mm, PREG_SET_ORDER);
$selections = [];
foreach ($mm as $x) {
    $selections[$x[1]] ??= [$x[2]];
}
$payload = http_build_query(['_token' => csrf($h), 'selections' => $selections]);
echo 'Positions: '.count($selections).". Firing {$parallel} simultaneous submissions across ".count($targets)." worker(s)...\n";

$multi = curl_multi_init();
$handles = [];
for ($n = 0; $n < $parallel; $n++) {
    $ch = curl_init($targets[$n % count($targets)].'/ballot/submit');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $jar, CURLOPT_POSTFIELDS => $payload]);
    curl_multi_add_handle($multi, $ch);
    $handles[] = $ch;
}
do {
    curl_multi_exec($multi, $running);
    curl_multi_select($multi);
} while ($running > 0);

$outcomes = [];
foreach ($handles as $ch) {
    $info = curl_getinfo($ch);
    $key = $info['http_code'].' '.parse_url((string) $info['redirect_url'], PHP_URL_PATH);
    $outcomes[$key] = ($outcomes[$key] ?? 0) + 1;
    curl_multi_remove_handle($multi, $ch);
}
ksort($outcomes);
print_r($outcomes);
[$h] = http('GET', "$base/ballot/receipt");
preg_match('/NIM-\d{4}-[0-9A-Z]{8}/', $h, $m);
echo 'Receipt: '.($m[0] ?? 'none')."\n";
@unlink($jar);
