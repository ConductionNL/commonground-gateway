<?php

namespace App\Service;

use DateTime;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Twig\Environment;


class TranslationService
{
    private SessionInterface $sessionInterface;
    private Environment $twig;

    public function __construct(SessionInterface $sessionInterface,Environment $twig)
    {
        $this->sessionInterface = $sessionInterface;
        $this->twig = $twig;
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

            if ($value === [] && $newKey != 'results') {
                unset($result[$newKey]);
            }
        }

        return $result;
    }

    /**
     * This function hydrates an array with the values of another array bassed on a mapping diffined in dot notation, with al little help from https://github.com/adbario/php-dot-notation and twig
     *
     * @param array $destination the array that the values are inserted into
     * @param array $source      the array that the values are taken from
     * @param array $mapping     the array that determines how the mapping takes place
     *
     * @return array
     */
    public function twigHydrator(array $source, array $mapping): array
    {
        // We are using dot notation for array's so lets make sure we do not intefene on the . part
        $destination = $this->encodeArrayKeys($source, '.', '&#2E');

        // lets get any drops
        $drops = [];
        if(array_key_exists('_drop',$mapping)){
            $drops = $mapping['_drops'];
            unset($mapping['_drops']);
        }

        // Lets turn  destination into a dat array
        $destination = new \Adbar\Dot($destination);

        // Lets use the mapping to hydrate the array
        foreach ($mapping as $key => $value) {
            $destination[$key] = castValue($this->twig->createTemplate($value)->render(['source'=>$source]));
        }

        // Lets remove the drops (is anny
        foreach ($drops as $drop){
            if($destination->has($drop)){
                $destination->clear($drop);
            }
            else{
                // @todo throw error?
            }
        }

        // Let turn the dot array back into an array
        $destination = $destination->all();
        $destination = $this->encodeArrayKeys($destination, '&#2E', '.');

        return $destination;
    }

    /**
     * This function cast a value to a specific value type
     *
     * @param string $value
     * @return void
     */
    public function castValue(string $value)
    {
        // Find the format for this value
        // @todo this should be a regex
        if (strpos($value, '|')) {
            $values = explode('|', $value);
            $value = trim($values[0]);
            $format = trim($values[1]);
        }
        else{
            return $value;
        }

        // What if....
        if(!isset($format)){
            return $value;
        }

        // Lets cast
        switch ($format){
            case 'string':
                return  strval($value);
            case 'bool':
            case 'boolean':
                return  boolval($value);
            case 'int':
            case 'integer':
                return  intval($value);
            case 'float':
                return  floatval($value);
            case 'array':
                return  (array) $value;
            case 'date':
                return  new DateTime($value);
            case 'url':
                return  urlencode(($value);
            case 'rawurl':
                return  rawurlencode(($value);
            case 'base64':
                return  base64_encode(($value);
            case 'json':
                return  json_encode($value);
            case 'xml':
                $xmlEncoder = new XmlEncoder();
                return  $xmlEncoder->decode($value, 'xml');
            default:
                //@todo throw error
        }
    }

    /**
     * This function hydrates an array with the values of another array bassed on a mapping diffined in dot notation, with al little help from https://github.com/adbario/php-dot-notation.
     *
     * @deprecated
     * @depredated
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
        foreach ($mapping as $replace => $search) {
            if (strpos($replace, '$') !== false && strpos($search, '$') !== false) {
                $iterator = 0;
                if ($source->has(str_replace('$', $iterator, $search))) {
                    while ($source->has(str_replace('$', $iterator, $search))) {
                        $mapping[str_replace('$', "$iterator", $replace)] = str_replace('$', "$iterator", $search);
                        $iterator++;
                    }
                } else {
                    $mapping[preg_replace('/\.[^.$]*?\$[^.$]*?\./', '', $replace)] = preg_replace('/\.[^.$]*?\$[^.$]*?\./', '', $search);
                }
                unset($mapping[$replace]);
                // todo: also unset the old variable in $destination
            }
        }

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
                // Make sure we don't transform (wrong type) input like integers to string. So validaterService throws a must be type x error when needed!
                $destination[$replace] = $source[$search] ?? ($destination[$replace]) ?? null;
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
            } elseif (strpos($format, 'concatenation') !== false) {
                $separator = substr($format, strlen('concatenation') + 1);
                $separator = str_replace('&nbsp;', ' ', $separator);
                $searches = explode('+', $search);
                $result = '';
                foreach ($searches as $subSearch) {
                    $value = is_array($source[$subSearch]) ? implode(', ', $source[$subSearch]) : $source[$subSearch];
                    $result .= isset($source[$subSearch]) ? ($value != '' ? $separator.$value : $value) : '';
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
     * this functions has been removed in favor of the new dataservice
     *
     * Creates a list of replacebale variables for use in translations.
     *
     * With a litle help from https://github.com/FakerPHP/Faker
     *
     * @deprecated
     * @depredated
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
     * this functions has been removed in favor of the new dataservice
     *
     * Creates a list of replacebale variables for use in translations.
     *
     * With a litle help from https://github.com/FakerPHP/Faker
     *
     * @deprecated
     * @depredated
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
