<?php

namespace App\Service;

use DateTime;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class TranslationService
{
    private SessionInterface $sessionInterface;

    public function __construct(SessionInterface $sessionInterface)
    {
        $this->sessionInterface = $sessionInterface;
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

            if ($value === []) {
                unset($result[$newKey]);
            }
        }

        return $result;
    }

    private function hasKeys($keys, $value): bool
    {
        $key = array_shift($keys);
        if(count($keys) > 1 && isset($value[$key])) {
            return $this->hasKeys($keys, $value);
        } else {
            return isset($value[$key]);
        }
    }

    public function getIterator (string $key, array $keys, array $value): int
    {
        $iterator = 0;
        if(strpos($key, '$') !== false){
            while(isset($value[str_replace('$', $iterator, $key)])) {
//                if($this->hasKeys($keys, $value[str_replace('$', $iterator, $key)])){
                    $iterator++;
//                }
            }
        } elseif(isset($value[$key])) {
            return $this->getIterator(array_shift($keys), $keys, $value[$key]);
        }
        return $iterator;
    }

    private function replaceDestination(array $mapping, string $replace, string $search, int $iterator, array $source, ?string $format): array
    {
        if($iterator == 1 && !isset($source['results'])) {
            $mapping[preg_replace('/\.[^.$]*?\$[^.$]*?\./', '', $replace)] = preg_replace('/\.[^.$]*?\$[^.$]*?\./', '', $search);
            unset($mapping[$replace]);
            return $mapping;
        }
        for($i = 0; $i < $iterator; $i++) {
            $newSearch = $format ? str_replace('$', $i, $search). " | $format" : str_replace('$', $i, $search);
            $mapping[str_replace('$', $i, $replace)] = $newSearch;
        }
        unset($mapping[$replace]);
        return $mapping;
    }

    public function getIterativeKeys(array $source, array $mapping): array
    {
        foreach($mapping as $replace => $search) {
            $pre = explode('|', $search);
            $search = trim($pre[0]);
            $format = trim($pre[1]);
            if (strpos($replace, '$') !== false && strpos($search, '$') !== false) {
                $explodedKey = explode('.', $search);
                $iterator = $this->getIterator(array_shift($explodedKey), $explodedKey, $source);
                $mapping = $this->replaceDestination($mapping, $replace, $search, $iterator, $source, $format);
            }
        }
        return $mapping;
    }

    public function getRecursive(array $source, array $search): array
    {
        if(count($search) > 1) {
            return $this->getRecursive($source[array_shift($search)], $search);
        } elseif(isset($source[array_shift($search)])) {
            return $source[array_shift($search)];
        }
        return [];
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
        $source = new \Adbar\Dot($source, true);
//        foreach($mapping as $replace => $search) {
//            if (strpos($replace, '$') !== false && strpos($search, '$') !== false) {
//                $iterator = 0;
//                if($source->has(str_replace('$', $iterator, $search))){
//                    while ($source->has(str_replace('$', $iterator, $search))) {
//                        $mapping[str_replace('$', "$iterator", $replace)] = str_replace('$', "$iterator", $search);
//                        $iterator++;
//                    }
//                } else {
//                    $mapping[preg_replace('/\.[^.$]*?\$[^.$]*?\./', '', $replace)] = preg_replace('/\.[^.$]*?\$[^.$]*?\./', '', $search);
//                }
//                unset($mapping[$replace]);
//                // todo: also unset the old variable in $destination
//            }
//        }
        $mapping = $this->getIterativeKeys($source->all(), $mapping);

        // Lets use the mapping to hydrate the array
        foreach ($mapping as $replace => $search) {
            if (strpos($search, '|')) {
                $searches = explode('|', $search);
                $search = trim($searches[0]);
                $format = trim($searches[1]);
            }
            if (!isset($format)) {
                // Make sure we don't transform (wrong type) input like integers to string. So validaterService throws a must be type x error when needed!
                $destination[$replace] = $source[$search] ?? ($destination[$replace]) ?? null;
                unset($destination[$search]);
            } elseif ($format == 'string') {
                $destination[$replace] = isset($source[$search]) ? (string) $source[$search] : ((string) $destination[$replace]) ?? null;
                unset($destination[$search]);
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
                if($sourceSub->has('results')){
                    $sourceSub = array_values($sourceSub->get('results'));
                }
                $destination[$replace] = $sourceSub;
            } elseif ($format == 'array') {
                $destination[$replace] = $this->getRecursive($source->all(), explode('.', $search)) ?? $destination[$replace] ?? [];
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
            'death_in_municipality' => 'Overlijden in gemeente',
            'intra_mun_relocation'  => 'Binnengemeentelijke verhuizing',
            'inter_mun_relocation'  => 'Inter-gemeentelijke verhuizing',
            'emigration'            => 'Emigratie',
            'resettlement'          => 'Hervestiging',
            'birth'                 => 'Geboorte',
            'confidentiality'       => 'Verstrekkingsbeperking',
            'commitment'            => 'Huwelijk/Geregistreerd partnerschap',
            'discovered_body'       => 'Lijkvinding',
            'acknowledgement'       => 'Erkenning',
            'marriage'              => 'Huwelijk',
            'incomplete'            => 'Incompleet',
            'created'               => 'Opgenomen',
            'processing'            => 'In behandeling',
            'on_hold'               => 'In wachtkamer',
            'processed'             => 'Verwerkt',
            'cancelled'             => 'Geannuleerd',
            'deleted'               => 'Verwijderd',
            'refused'               => 'Geweigerd',
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
//        var_dump($subject);
        return $subject;
    }

    public function addPrefix($data, ?string $prefix = null)
    {
        if(is_array($data) && isset($data['results'])) {
            $data['results'] = $this->addPrefix($data['results'], $prefix);
            return $data;
        }
        elseif(is_array($data)){
            $result = [];
            foreach ($data as $key => $value) {
                $result[$this->addPrefix($key, $prefix)] = is_array($value) ? $this->addPrefix($value, $prefix): $value;
            }
            return $result;
        } else {
            return $prefix.$data;
        }
    }
}
