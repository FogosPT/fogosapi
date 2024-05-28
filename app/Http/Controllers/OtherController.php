<?php

namespace App\Http\Controllers;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Redis;

class OtherController extends Controller
{
    public function getMobileContributors()
    {
        $exists = Redis::get('mobile:contributors');
        if ($exists) {
            return json_decode($exists, true);
        }
        $url = 'https://api.github.com/repos/FogosPT/fogosmobile/contributors';

        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->request('GET', $url);
        } catch (ClientException $e) {
            return ['error' => $e->getMessage()];
        } catch (RequestException $e) {
            return ['error' => $e->getMessage()];
        }

        $body = $response->getBody();
        $result = json_decode($body->getContents(), true);

        foreach ($result as &$r) {
            try {
                $responseContributors = $client->request('GET', $r['url']);
            } catch (ClientException $e) {
                return ['error' => $e->getMessage()];
            } catch (RequestException $e) {
                return ['error' => $e->getMessage()];
            }

            $bodyContributors = $responseContributors->getBody();
            $data = json_decode($bodyContributors->getContents(), true);

            $r = array_merge($r, $data);
        }

        Redis::set('mobile:contributors', json_encode($result), 'EX', 108000);

        return $result;
    }
}
