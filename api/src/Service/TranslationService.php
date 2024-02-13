<?php

namespace App\Service;

use Adbar\Dot;
use App\Entity\Gateway as Source;
use App\Entity\Synchronization;
use CommonGateway\CoreBundle\Service\CallService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

/**
 * @Author Ruben van der Linde <ruben@conduction.nl>, Robert Zondervan <robert@conduction.nl>, Barry Brands <barry@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Sarai Misidjan <sarai@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class TranslationService
{
    private SessionInterface $sessionInterface;

    public function __construct(
        RequestStack                            $requestStack,
        private readonly EntityManagerInterface $entityManager,
        private readonly CallService            $callService
    )
    {
        try {
            $this->session = $requestStack->getSession();
        } catch (SessionNotFoundException $exception) {
            $this->session = new Session();
        }
    }

    private function encodeArrayKeys($array, string $toReplace, string $replacement): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = str_replace($toReplace, $replacement, $key);

            if (\is_array($value) && $value) {
                $result[$newKey] = $this->encodeArrayKeys($value, $toReplace, $replacement);
                continue;
            }
            $result[$newKey] = $value;

            // todo: With this if statement it is impossible to do the following put: "telefoonnummers": []
            // todo @rjzondervan: this change might impact work done for Nijmegen. This code i removed was added by you in december 2021.
//            if ($value === [] && $newKey != 'results') {
//                unset($result[$newKey]);
//            }
        }

        return $result;
    }

    /**
     * Decides wether or not an array is associative.
     *
     * @param array $array The array to check
     *
     * @return bool Wether or not the array is associative
     */
    private function isAssociative(array $array)
    {
        if ([] === $array) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Recursively scans keys for the occurence of the numeric key identifier and either replaces them by numeric keys or removes them.
     *
     * @param string $search  The search key to update
     * @param string $replace The replace key to update
     * @param Dot    $source  The source to scan
     * @param array  $mapping
     *
     * @return mixed
     */
    private function addNumericKeysRecursive(string $search, string $replace, Dot $source, array $mapping)
    {
        if (strpos($search, '.$') !== false && is_array($source[substr($search, 0, strpos($search, '.$'))]) && !$this->isAssociative($source[substr($search, 0, strpos($search, '.$'))])) {
            // if there is a numeric array, replace the keys
            foreach ($source[substr($search, 0, strpos($search, '.$'))] as $key => $value) {
                $newSearch = preg_replace('/\.\$/', ".$key", $search, 1);
                $newReplace = strpos(substr($replace, 0, strpos($replace, '.$') + 3), '.$!') !== false ? preg_replace('/\.\$!/', ".$key", $replace, 1) : preg_replace('/\.\$/', ".$key", $replace, 1);
                $mapping[$newReplace] = $newSearch;
                $mapping = $this->addNumericKeysRecursive($newSearch, $newReplace, $source, $mapping);
            }
            unset($mapping[$replace]);
        } elseif (strpos($search, '.$') !== false) {
            // if there is no array, remove the keys, or only set 0 (if the numeric key is enforced by !)
            $newSearch = preg_replace('/\.\$/', '', $search, 1);
            $newReplace = strpos(substr($replace, 0, strpos($replace, '.$') + 3), '.$!') !== false ? preg_replace('/\.\$!/', '.0', $replace, 1) : preg_replace('/\.\$/', '', $replace, 1);
            $mapping[$newReplace] = $newSearch;
            $mapping = $this->addNumericKeysRecursive($newSearch, $newReplace, $source, $mapping);
            unset($mapping[$replace]);
        }

        return $mapping;
    }

    /**
     * Update mapping for numeric arrays. Replaces .$ by the keys in a numeric array, or removes it in the case of an associative array.
     *
     * @param array $mapping The mapping to update
     * @param Dot   $source  The source data
     *
     * @return array
     */
    public function iterateNumericArrays(array $mapping, Dot $source): array
    {
        foreach ($mapping as $replace => $search) {
            $mapping = $this->addNumericKeysRecursive($search, $replace, $source, $mapping);
        }

        return $mapping;
    }

    /**
     * Finds an uuid for an url, returns the uuid in the url if there is no equivalent found in the synchronisations of the gateway.
     *
     * @param string|null $url The url to scan
     *
     * @return false|mixed|string The uuid of the object the url refers to
     */
    public function getUuidFromUrl(?string $url)
    {
        $array = explode('/', $url);
        /* @todo we might want to validate against uuid and id here */
        $sourceId = end($array);
        $synchronizations = $this->entityManager->getRepository(Synchronization::class)->findBy(['sourceId' => $sourceId]);
        if (count($synchronizations) > 0 && $synchronizations[0] instanceof Synchronization) {
            return $synchronizations[0]->getObject()->getId()->toString();
        }

        return null;
    }

    public function getSourceFromUrl(string $url): ?Source
    {
        $url = substr($url, strlen('https://'));
        $split = explode('/', $url);
        $baseUrl = '';

        $i = 0;
        while ($split[$i]) {
            $baseUrl .= $baseUrl ? '/'.$split[$i] : $split[$i];
            $sources = $this->entityManager->getRepository(Source::class)->findBy(['location' => 'https://'.$baseUrl]);
            if ($sources && $sources[0] instanceof Source) {
                return $sources[0];
            }
            $i++;
        }

        return null;
    }

    public function getDataFromUrl(string $url): ?string
    {
        $source = $this->getSourceFromUrl($url);

        if (!$source) {
            return null;
        }

        $endpoint = substr($url, strlen($source->getLocation()));
        $result = $this->callService->call($source, $endpoint);

        return base64_encode($result->getBody()->getContents());
    }

    /**
     * This function hydrates an array with the values of another array bassed on a mapping diffined in dot notation, with al little help from https://github.com/adbario/php-dot-notation.
     *
     * @param array $destination the array that the values are inserted into
     * @param array $source      the array that the values are taken from
     * @param array $mapping     the array that determines how the mapping takes place
     *
     * @return array
     */
    public function dotHydrator(array $destination, array $source, array $mapping): array
    {
        $destination = $this->encodeArrayKeys($destination, '.', '&#2E');
        $source = $this->encodeArrayKeys($source, '.', '&#2E');

        // Lets turn the two arrays into dot notation
        $destination = new \Adbar\Dot($destination);
        $source = new \Adbar\Dot($source);
        $mapping = $this->iterateNumericArrays($mapping, $source);
        // Lets use the mapping to hydrate the array
        foreach ($mapping as $replace => $search) {
            if (strpos($search, '|')) {
                $searches = explode('|', $search);
                $search = trim($searches[0]);
                $format = trim($searches[1]);
            }

            if (isset($source[$search]['@xsi:nil'])) {
                unset($destination[$search]);
            } elseif (!isset($format)) {
                // Make sure we don't transform (wrong type) input like integers to string. So validatorService throws a must be type x error when needed!
                $destination[$replace] = $source[$search] ?? $destination[$replace] ?? null;
            } elseif ($format == 'string') {
                $destination[$replace] = isset($source[$search]) ? (string) $source[$search] : ((string) $destination[$replace]) ?? null;
            } elseif ($format == 'json') {
                $destination[$replace] = isset($source[$search]) ? json_decode($source[$search], true) : ($destination[$replace]) ?? null;
            } elseif ($format == 'xml') {
                $xmlEncoder = new XmlEncoder();
                $destination[$replace] = isset($source[$search]) ? $xmlEncoder->decode($source[$search], 'xml') : ($destination[$replace]) ?? null;
            } elseif ($format == 'bool') {
                $destination[$replace] = isset($source[$search]) ? (bool) $source[$search] : ((bool) $destination[$replace]) ?? null;
            } elseif ($format == 'array' && $search == 'object') {
                $destination[$replace] = $source;
            } elseif ($format == 'array' && $search == 'object(s)') {
                $sourceSub = new \Adbar\Dot($source, true);
                if ($sourceSub->has('results')) {
                    $sourceSub = array_values($sourceSub->get('results'));
                }
                $destination[$replace] = $sourceSub;
            } elseif ($format == 'date') {
                $datum = new DateTime(isset($source[$search]) ? (string) $source[$search] : ((string) $destination[$replace]) ?? null);
                $destination[$replace] = $datum->format('Y-m-d');
            } elseif ($format == 'datetimeutc') {
                $datum = new DateTime(isset($source[$search]) ? (string) $source[$search] : ((string) $destination[$replace]) ?? null);
                $destination[$replace] = $datum->format('Y-m-d\TH:i:s');
            } elseif ($format == 'uuidFromUrl') {
                $destination[$replace] = $this->getUuidFromUrl($source[$search]) ?? $destination[$replace] ?? null;
            } elseif ($format == 'download') {
                $destination[$replace] = $this->getDataFromUrl($source[$search]);
            } elseif (strpos($format, 'concatenation') !== false) {
                $separator = substr($format, strlen('concatenation') + 1);
                $separator = str_replace('&nbsp;', ' ', $separator);
                $searches = explode('+', $search);
                $result = '';
                foreach ($searches as $subSearch) {
                    if (str_starts_with($subSearch, '\'') && str_ends_with($subSearch, '\'')) {
                        $result .= trim($subSearch, '\'');
                        continue;
                    }
                    $value = is_array($source->get($subSearch)) ? implode(', ', $source->get($subSearch)) : $source->get($subSearch);
                    $result .= !empty($source->get($subSearch)) ? ($value != '' ? $separator.$value : $value) : '';
                }
                $destination[$replace] = $result ?: $destination[$replace];
            }
            unset($format);

            if ($destination[$replace] === [] || $destination[$replace] === '') {
                unset($destination[$replace]);
            }
        }

        // Let turn the dot array back into an array
        $destination = $destination->all();
        $destination = $this->encodeArrayKeys($destination, '&#2E', '.');

        return $destination;
    }

    /**
     * Creates a list of replacebale variables for use in translations.
     *
     * With a litle help from https://github.com/FakerPHP/Faker
     *
     * @return array
     */
    public function generalVariables(): array
    {
        // todo Caching
        $faker = \Faker\Factory::create();
        $now = new DateTime();

        $variables = [];

        // Faker example implementation
        $variables['faker_uuid'] = $faker->uuid();
        $variables['date'] = $now->format('Y-m-d');

        // Session example implementation
        $variables['session_organization_id'] = $faker->uuid();

        $variables['bsn'] = $this->sessionInterface->get('bsn');

        return $variables;
    }

    /**
     * Creates a list of replacebale variables for use in translations.
     *
     * With a litle help from https://github.com/FakerPHP/Faker
     *
     * @return array
     */
    public function translationVariables(array $variables = []): array
    {
        $variables = array_merge($variables, [
            // todo: taalhuizen doesn't like this, if you want to translate create Translation objects...
            //            'death_in_municipality' => 'Overlijden in gemeente',
            //            'intra_mun_relocation'  => 'Binnengemeentelijke verhuizing',
            //            'inter_mun_relocation'  => 'Inter-gemeentelijke verhuizing',
            //            'emigration'            => 'Emigratie',
            //            'resettlement'          => 'Hervestiging',
            //            'birth'                 => 'Geboorte',
            //            'confidentiality'       => 'Verstrekkingsbeperking',
            //            'commitment'            => 'Huwelijk/Geregistreerd partnerschap',
            //            'discovered_body'       => 'Lijkvinding',
            //            'acknowledgement'       => 'Erkenning',
            //            'marriage'              => 'Huwelijk',
            //            'incomplete'            => 'Incompleet',
            //            'created'               => 'Opgenomen',
            //            'processing'            => 'In behandeling',
            //            'on_hold'               => 'In wachtkamer',
            //            'processed'             => 'Verwerkt',
            //            'cancelled'             => 'Geannuleerd',
            //            'deleted'               => 'Verwijderd',
            //            'refused'               => 'Geweigerd',
            '<![CDATA['             => '',
            ']]>'                   => '',
            '<conductionPartners>'  => '',
            '</conductionPartners>' => '',
            '<conductionKinderen>'  => '',
            '</conductionKinderen>' => '',
            '<conductionOuders>'    => '',
            '</conductionOuders>'   => '',
        ]);

        //@todo laten we deze vandaag lekker concreet houden
        $variables['marriage'] = 'huwelijk';

        return $variables;
    }

    /**
     * This function parses a string for in string translations (optional)  an variable replacment.
     *
     * With a litle help from https://stackoverflow.com/questions/18197348/replacing-variables-in-a-string
     *
     * @param string|array $subject        The string to translate
     * @param bool         $translate      Whether or not to also translate to string (defaults to true)
     * @param array        $variables      Additional variables to replace
     * @param string       $escapeChar     The escape charater to use (default to @)
     * @param string|null  $errPlaceholder
     *
     * @return string|string[]|null
     */
    public function parse(
        $subject,
        bool $translate = true,
        array $translationVariables = [],
        string $escapeChar = '@',
        string $errPlaceholder = null
    ) {
        // TODO should be done with array_walk_recursive
        if (is_array($subject)) {
            $result = [];
            foreach ($subject as $key => $value) {
                $result[$this->parse($key, $translate, $translationVariables, $escapeChar, $errPlaceholder)] = $this->parse($value, $translate, $translationVariables, $escapeChar, $errPlaceholder);
//                unset($subject[$key]);
            }

            return $result;
        }

        // We only translate strings
        if (!is_string($subject)) {
            return $subject;
        }

        $esc = preg_quote($escapeChar);
        $expr = "/
        $esc$esc(?=$esc*+{)
      | $esc{
      | {(\w+)}
    /x";

        $variables = array_merge($translationVariables, $this->generalVariables());

        $callback = function ($match) use ($variables, $escapeChar, $errPlaceholder) {
            switch ($match[0]) {
                case $escapeChar.$escapeChar:
                    return $escapeChar;

                case $escapeChar.'{':
                    return '{';

                default:
                    if (isset($variables[$match[1]])) {
                        return $variables[$match[1]];
                    }

                    return isset($errPlaceholder) ? $errPlaceholder : $match[0];
            }
        };

        // Lets do variable replacement (done on an {x} for y replacement)
        $subject = preg_replace_callback($expr, $callback, $subject);

        // Lets do translation  (done on a x for y replacement)
        if ($translate) {
            $subject = strtr($subject, $this->translationVariables($translationVariables));
        }

        return $subject;
    }

    public function addPrefix($data, ?string $prefix = null)
    {
        if (is_array($data) && isset($data['results'])) {
            $data['results'] = $this->addPrefix($data['results'], $prefix);

            return $data;
        } elseif (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$this->addPrefix($key, $prefix)] = is_array($value) ? $this->addPrefix($value, $prefix) : $value;
            }

            return $result;
        } else {
            return $prefix.$data;
        }
    }
}
