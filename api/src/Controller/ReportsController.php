<?php


namespace App\Controller;


use App\Service\EavService;
use Conduction\CommonGroundBundle\Service\SerializerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
Use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Filesystem\Filesystem;
use Psr\Http\Message\RequestInterface;
use function GuzzleHttp\json_decode;


/**
 * @Route("/api/reports")
 */
class ReportsController extends AbstractController
{
    private SerializerService $serializerService;
    private RequestInterface $request;


    public function __contstruct(SerializerService $serializerService, RequestInterface $request)
    {
        $this->serializerService = $serializerService;
        $this->request = $request;
    }

    /**
     * @Route("/students")
     */
    public function StudentsAction(): BinaryFileResponse
    {

        $data = [
            ['ID deelnemer', 'Datum intake', 'Status', 'Roepnaam', 'Tussenvoegsel', 'Achternaam', 'Taalhuis'],
        ];


        // Get results an loop trough them to add them to data


        return $this->createCsvResponce('students',$data);
    }

    /**
     * @Route("/learning_needs")
     */
    public function LearningNeedsAction(): BinaryFileResponse
    {

        $data = [
            ['ID leervraag', 'Kort omschrijving', 'Motivatie', 'Werkwoord', 'Onderwerp', 'Onderwerp: Anders, namelijk: ', 'Toepassing', 'Toepassing: Anders, namelijk:', 'Niveau', 'Niveau: Anders, namelijk: ', 'Gewenste aanbod', 'Geadviseerd aanbod', 'Is er een verschil tussen wens en advies', 'Is er een verschil tussen wens en advies: a, want: anders', 'Afspraken', 'ID deelnemer', 'Datum intake', 'Status', 'Roepnaam', 'Tussenvoegsel', 'Achternaam', 'Taalhuis', 'ID Aanbieder', 'Aanbieder', 'Aanbieder: Anders, namelijk:', 'Start deelname', 'Einde deelname', 'Reden einde deelname', 'Naam aanbod', 'Type curcus', ' Leeruitkomst Werkwoord', 'Leeruitkomst Onderwerp', 'Leeruitkomst Toepassing', 'Leeruitkomst Niveau', 'Toets', ' Toetsdatum', 'Toelichting']
        ];


        // Get results an loop trough them to add them to data


        return $this->createCsvResponce('learning_needs'.$data);
    }

    /**
     * Create a CSV responce
     *
     * @param array $data the date that you want loaded into the CSV file
     * @return BinaryFileResponse the csv file as a binary file reponce
     */
    public function createCsvResponce(string $name, array $data): BinaryFileResponse
    {

        $tmpFileName = (new Filesystem())->tempnam(sys_get_temp_dir(), 'sb_');
        $tmpFile = fopen($tmpFileName, 'wb+');
        if (!\is_resource($tmpFile)) {
            throw new \RuntimeException('Unable to create a temporary file.');
        }

        foreach ($data as $line) {
            fputcsv($tmpFile, $line, ';');
        }

        $date = new \DateTime('2000-01-01');
        $date = $date->format('Ymd_His');

        $response = $this->file($tmpFileName, $name.'_'.$date.'.csv');
        $response->headers->set('Content-type', 'application/csv');

        fclose($tmpFile);

        return $response;
    }



}
