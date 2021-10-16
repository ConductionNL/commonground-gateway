<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @Route("/api/reports")
 */
class ReportsController extends AbstractController
{
    private SerializerInterface $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * @Route("/students")
     */
    public function StudentsAction(EntityManagerInterface $em): Response
    {
        $entity = $this->getDoctrine()->getRepository('App:Entity')->findOneBy(['name'=>'students']);
        $results = $this->getDoctrine()->getRepository('App:ObjectEntity')->findByEntity($entity);

        $headers = [
            'ID deelnemer', 'Datum intake', 'Status', 'Roepnaam', 'Tussenvoegsel', 'Achternaam', 'Taalhuis',
        ];

        // Get results an loop trough them to add them to data
        $data = [];
        foreach ($results as $result) {
            $result = $result->toArray();

            $data[] = [
                'ID deelnemer'  => $result['id'] ?? null,
                'Datum intake'  => $result['intake']['date'] ?? null,
                'Status'        => $result['intake']['status'] ?? null,
                'Roepnaam'      => $result['person']['givenName'] ?? null,
                'Tussenvoegsel' => $result['person']['additionalName'] ?? null,
                'Achternaam'    => $result['person']['familyName'] ?? null,
                'Taalhuis'      => $result['languageHouse']['name'] ?? null,
            ];
        }

        return $this->createCsvResponse('students', $headers, $data);
    }

    /**
     * @Route("/learning_needs")
     */
    public function LearningNeedsAction(EntityManagerInterface $em): Response
    {
        $entity = $this->getDoctrine()->getRepository('App:Entity')->findOneBy(['name'=>'learningNeeds']);
        $results = $em->getRepository('App:ObjectEntity')->findByEntity($entity);

        $headers = ['ID leervraag',
            'Kort omschrijving',
            'Motivatie',
            'Werkwoord',
            'Onderwerp', 'Onderwerp: Anders, namelijk: ', 'Toepassing', 'Toepassing: Anders, namelijk:', 'Niveau', 'Niveau: Anders, namelijk: ', 'Gewenste aanbod', 'Geadviseerd aanbod', 'Is er een verschil tussen wens en advies', 'Is er een verschil tussen wens en advies: a, want: anders', 'Afspraken', 'ID deelnemer', 'Datum intake', 'Status', 'Roepnaam', 'Tussenvoegsel', 'Achternaam', 'Taalhuis', 'ID Aanbieder', 'Aanbieder', 'Aanbieder: Anders, namelijk:', 'Start deelname', 'Einde deelname', 'Reden einde deelname', 'Naam aanbod', 'Type curcus', ' Leeruitkomst Werkwoord', 'Leeruitkomst Onderwerp', 'Leeruitkomst Toepassing', 'Leeruitkomst Niveau', 'Toets', ' Toetsdatum', 'Toelichting',
        ];

        $data = [];
        // Get results an loop trough them to add them to data
        foreach ($results as $result) {
            $result = $result->toArray();

            $data[] = [
                $result['id'],
            ];
        }

        return $this->createCsvResponse('learning_needs', $headers, $data);
    }

    /**
     * Create a CSV responce.
     *
     * @param array $headers
     * @param array $data    the date that you want loaded into the CSV file
     *
     * @return BinaryFileResponse the csv file as a binary file reponce
     */
    public function createCsvResponse(string $name, array $headers, array $data): Response
    {
        $result = $this->serializer->serialize($data, 'csv', [CsvEncoder::DELIMITER_KEY => ';', CsvEncoder::HEADERS_KEY => $headers]);

        $date = new \DateTime();
        $date = $date->format('Ymd_His');
        $response = new Response($result, 200, [
            'Content-type'=> 'application/csv',
        ]);
        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, "{$name}_{$date}.csv");
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
