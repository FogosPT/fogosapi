<?php

namespace App\Http\Controllers;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Redis;
use Illuminate\Routing\Controller;

class OtherController extends Controller
{
    public function getMobileContributors()
    {
        $exists = Redis::get('mobile:contributors');
        if ($exists) {
            return json_decode($exists, true);
        }
        $url = 'https://api.github.com/repos/FogosPT/fogosmobile/contributors';

        // Without timeouts a slow GitHub held the PHP worker indefinitely
        // (1 + N sequential calls on a cache miss).
        $client = new \GuzzleHttp\Client(['connect_timeout' => 5, 'timeout' => 10]);

        try {
            $result = json_decode($client->request('GET', $url)->getBody()->getContents(), true);

            if (!is_array($result)) {
                return response()->json(['success' => false, 'error' => 'upstream_invalid'], 502);
            }

            foreach ($result as &$r) {
                $data = json_decode($client->request('GET', $r['url'])->getBody()->getContents(), true);
                $r = array_merge($r, is_array($data) ? $data : []);
            }
            unset($r);
        } catch (GuzzleException $e) {
            return response()->json(['success' => false, 'error' => 'upstream_unavailable'], 502);
        }

        Redis::set('mobile:contributors', json_encode($result), 'EX', 108000);

        return $result;
    }
}
