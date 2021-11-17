<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ExportService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }


    public function handleExports($type)
    {
        $export = [];
        switch ($type) {
            case 'gateways':
                $export = array_merge($this->exportGateway(), $export);
                break;
            case 'entities':
                $export = array_merge($this->exportEntity(), $export);
                break;
            case 'all':
            default:
                $export = array_merge($this->exportEntity(), $export);
                $export = array_merge($this->exportGateway(), $export);
                break;
        }


        $yaml = Yaml::dump($export, 4);

        $response = new Response($yaml, 200, [
            'Content-type' => 'text/yaml',
        ]);

        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, "export.yaml");
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    public function exportGateway()
    {
        $array['App\Entity\Gateway'] = [];
        $objects = $this->em->getRepository('App:Gateway')->findAll();

        // Filter empty values
        foreach ($objects as &$object) {
            $array['App\Entity\Gateway'][$object->getId()->toString()] = $object->export();
        }

        return $array;
    }

    public function exportEntity()
    {
        $array['App\Entity\Entity'] = [];
        $objects = $this->em->getRepository('App:Entity')->findAll();

        // Filter empty values
        foreach ($objects as &$object) {
            $array['App\Entity\Entity'][$object->getId()->toString()] = $object->export();
        }

        return $array;
    }
}
