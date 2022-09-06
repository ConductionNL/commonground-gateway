<?php

namespace App\Service;


use App\Exception\GatewayException;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use App\Exception\GatewayException;

/**
 * The data service aims at providing an acces layer to request, session and user information tha can be accesed and changed from differend contexts e.g. actionHandels, Events etc
 */
class DataService
{
    private SessionInterface $session;
    private Request $request;
    private Security $security;

    public function __construct(SessionInterface $session, RequestStack $requestStack, Security $security)
    {
        $this->session = $session;
        $this->request = $requestStack->getCurrentRequest();
        $this->security = $security;
        $this->user = $this->security->getUser();
    }

    /*
     * This function should be call on the start and end of a call to remove any call specific data from the session
     */
    function clearCallData(){
        $this->sesion->clear('data');
    }

    /**
     * Returns the data from the current requests and decodes it if necicary
     *
     * @return array
     *
     * @todo more content types ?
     * @todo check for specific error when decoding
     */
    public function getDataFromRequest(): array
    {

        // If we already have the data in the session then we do NOT want to rearange it
        if($data = $this->session->get('data',false)){
            return $data;
        }

        $content = $this->request->getContent();
        $contentType = $this->getRequestType('content-type');

        // lets transform the data from the request
        switch ($contentType) {
            case 'json':
            case 'jsonhal':
            case 'jsonld':
                $data = json_decode($content, true);
                break;
            case 'xml':
                $xmlEncoder = new XmlEncoder();
                $xml = $xmlEncoder->decode($content, $contentType);
                // otherwise xml will throw its own error bypassing our exception handling
//        libxml_use_internal_errors(true);
                // string to xml object, encode that to json then decode to array
//        $xml = simplexml_load_string($content);
                // if xml is false get errors and throw exception
                if ($xml === false) {
                    $errors = 'Something went wrong decoding xml:';
                    foreach (libxml_get_errors() as $e) {
                        $errors .= ' '.$e->message;
                    }

                    throw new GatewayException($errors, null, null, ['data' => $content, 'path' => 'Request body', 'responseType' => Response::HTTP_UNPROCESSABLE_ENTITY]);
                }

                $data = json_decode(json_encode($xml), true);
            default:
                throw new GatewayException('Unsupported content type', null, null, ['data' => $content, 'path' => null, 'responseType' => Response::HTTP_UNSUPPORTED_MEDIA_TYPE]);
        }

        // Stuf it into the session so we wont have to do this again
        $this->session->set('data',$data);

        return $data;
    }
}
