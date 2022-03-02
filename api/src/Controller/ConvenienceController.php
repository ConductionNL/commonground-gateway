<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use GuzzleHttp\Client;
use Symfony\Component\Yaml\Yaml;

class ConvenienceController extends AbstractController
{
    /**
     * @Route("/admin/load/{type}", name="dynamic_route_load_type")
     */
    public function loadAction(Request $request, string $type): Response {
      switch ($type) {
        case 'redoc':
          $request->query->get('url') ? $url = $request->query->get('url') : $errMsg = 'No url given';
          if ($url) {
            $client = new Client();
            $response = $client->get($url);
            $redoc = Yaml::parse($response->getBody()->getContents());
            $status = $this->persistRedoc($redoc);
            // var_dump($redoc);die;
          }
          die;
          break;

      }

      return new Response;
    }

    private function persistRedoc(string $redoc): string {

      return 'Went good' // or bad
    }
}
