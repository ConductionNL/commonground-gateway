<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use PhpParser\Node\Expr\Array_;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;


/*
 * This servers takes an external oas document and turns that into an gateway + eav structure
 */
class OasParserService
{
    private EntityManagerInterface $em;
    private CommonGroundService $commonGroundService;

    public function __construct(EntityManagerInterface $em, CommonGroundService $commonGroundService)
    {
        $this->em = $em;
        $this->commonGroundService = $commonGroundService;


    }

    public function getOAS(string $url): Array
    {
        $oas = [];

        // @todo validate on url
        $pathinfo = parse_url($url);
        $extension = explode('.', $pathinfo['path']);
        $extension = end($extension);
        $pathinfo['extension'] = $extension;

        // file_get_contents(
        $file = file_get_contents($url);

        switch ($pathinfo['extension']) {
            case "yaml":
                $oas = Yaml::parse($file);
                break;
            case "json":
                $oas = json_decode($file, true);
                break;
            default:
               // @todo throw error
        }

        // Do we have schemse?
        //if(!array_key_exists('components',$oas) || !array_key_exists('components',$oas['components']) ) // @throw error

        // Do we have paths?
        if(!array_key_exists('paths',$oas)) // @throw error

        var_dump($oas);

        return $oas;
    }
}
