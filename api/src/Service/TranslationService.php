<?php

namespace App\Service;

use Conduction\CommonGroundBundle\Service\CommonGroundService;
use App\Entity\Soap;
use \App\Service\EavService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class TranslationService
{
    /**
     * This function hydrates an array with the values of another array bassed on a mapping diffined in dot notation, with al little help from https://github.com/adbario/php-dot-notation
     *
     * @param array $destination the array that the values are inserted into
     * @param array $source the array that the values are taken from
     * @param array $mapping the array that the values are taken from
     * @return array
     */
    public function dotHydrator(array $destination, array $source, array $mapping): array
    {
        // Lets turn the two arrays into dot notation
        $destination = new \Adbar\Dot($destination);
        $source = new \Adbar\Dot($source);

        // Lets use the mapping to hydrate the array
        foreach($mapping as $search => $replace){
            $destination[$replace] = (string) $source[$search];
        }

        // Let turn the dot array back into an array
        $destination = $destination->all();

        return $destination;
    }



    function parse(
        /* string */ $subject,
                     array        $variables,
        /* string */ $escapeChar = '@',
        /* string */ $errPlaceholder = null
    ) {
        $esc = preg_quote($escapeChar);
        $expr = "/
        $esc$esc(?=$esc*+{)
      | $esc{
      | {(\w+)}
    /x";

        $callback = function($match) use($variables, $escapeChar, $errPlaceholder) {
            switch ($match[0]) {
                case $escapeChar . $escapeChar:
                    return $escapeChar;

                case $escapeChar . '{':
                    return '{';

                default:
                    if (isset($variables[$match[1]])) {
                        return $variables[$match[1]];
                    }

                    return isset($errPlaceholder) ? $errPlaceholder : $match[0];
            }
        };

        return preg_replace_callback($expr, $callback, $subject);
    }
}
