<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Yaml\Yaml;

/**
 * @Author Gino Kok, Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class ExportService
{
    private EntityManagerInterface $em;
    private Filesystem $fileSystem;

    public function __construct(EntityManagerInterface $em, Filesystem $filesystem)
    {
        $this->em = $em;
        $this->fileSystem = $filesystem;
    }

    public function handleExports($type, $method = 'download')
    {
        $export = [];
        switch ($type) {
            case 'gateways':
                $export = array_merge($this->exportGateway(), $export);
                break;
            case 'entities':
                $export = array_merge($this->exportEntity(), $export);
                break;
            case 'attributes':
                $export = array_merge($this->exportProperty(), $export);
                break;
            case 'soap':
                $export = array_merge($this->exportSoap(), $export);
                break;
            case 'all':
            default:
                $export = array_merge($this->exportProperty(), $export);
                $export = array_merge($this->exportEntity(), $export);
                $export = array_merge($this->exportGateway(), $export);
                $export = array_merge($this->exportSoap(), $export);
                break;
        }

        $yaml = Yaml::dump($export, 4);

        switch ($method) {
            case 'download':
                $response = new Response($yaml, 200, [
                    'Content-type' => 'text/yaml',
                ]);

                $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'export.yaml');
                $response->headers->set('Content-Disposition', $disposition);

                return $response;
                break;
            case 'file':
                $this->fileSystem->dumpFile('gateway/export.yaml', $yaml);

                return true;
                break;
        }
    }

    public function exportGateway()
    {
        $array['App\Entity\Gateway'] = [];
        $objects = $this->em->getRepository('App:Gateway')->findAll();

        foreach ($objects as &$object) {
            $array['App\Entity\Gateway'][$object->getId()->toString()] = $object->export();
        }

        return $array;
    }

    /**
     * @deprecated
     *
     * @return array
     */
    public function exportSoap()
    {
        $array['App\Entity\Soap'] = [];
        $objects = $this->em->getRepository('App:Soap')->findAll();

        foreach ($objects as &$object) {
            $array['App\Entity\Soap'][$object->getId()->toString()] = $object->export();
        }

        return $array;
    }

    public function exportProperty()
    {
        $array['App\Entity\Attribute'] = [];
        $objects = $this->em->getRepository('App:Attribute')->findAll();

        foreach ($objects as &$object) {
            $array['App\Entity\Attribute'][$object->getId()->toString()] = $object->export();
        }

        return $array;
    }

    public function exportEntity()
    {
        $array['App\Entity\Entity'] = [];
        $objects = $this->em->getRepository('App:Entity')->findAll();

        foreach ($objects as &$object) {
            $array['App\Entity\Entity'][$object->getId()->toString()] = $object->export();
        }

        return $array;
    }
}
