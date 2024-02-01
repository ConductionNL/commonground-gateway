<?php

namespace App\Service;

use App\Entity\Authentication;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS512;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Security;
use Twig\Environment;

/**
 * @Author Gino Kok, Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class AuthenticationService
{
    /**
     * @var SessionInterface The current session.
     */
    private SessionInterface $session;

    /**
     * @var Client A guzzle client.
     */
    private Client $client;

    /**
     * @param RequestStack $requestStack The current request stack.
     * @param EntityManagerInterface $entityManager The entity manager.
     * @param ParameterBagInterface $parameterBag The parameter bag.
     */
    public function __construct(
        RequestStack                            $requestStack,
        private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $parameterBag
    )
    {
        try {
            $this->session = $requestStack->getSession();
        } catch (SessionNotFoundException) {
            $this->session = new Session();
        }
        $this->client  = new Client();
    }

    /**
     * Writes a temporary file in the component file system.
     *
     * @param string $contents The contents of the file to write
     * @param string $type     The type of file to write
     *
     * @return string The location of the written file
     */
    public function writeFile(string $contents, string $type): string
    {
        $stamp = microtime().getmypid();
        file_put_contents(dirname(__FILE__, 3).'/var/'.$type.'-'.$stamp, $contents);

        return dirname(__FILE__, 3).'/var/'.$type.'-'.$stamp;
    }

    /**
     * Removes (temporary) files from the filesystem.
     *
     * @param array $files An array of file paths of files to delete
     */
    public function removeFiles(array $files): void
    {
        foreach ($files as $filename) {
            unlink($filename);
        }
    }

    public function authenticate(string $method, string $identifier, string $code): array
    {
        if (!$method || !$identifier) {
            throw new BadRequestException("Method and identifier can't be empty");
        }

        $authentication = $this->retrieveAuthentication($identifier);
        $this->session->set('authenticator', $authentication->getId()->toString());

        return $this->retrieveData($method, $code, $authentication);
    }

    public function retrieveData(string $method, string $code, Authentication $authentication): array
    {
        $redirectUrl = $this->session->get('redirectUrl', $this->parameterBag->get('defaultRedirectUrl'));

        switch ($method) {
            case 'oidc':
            case 'adfs':
                return $this->retrieveAdfsData($code, $authentication, $redirectUrl);
            case 'digid':
                break;
            default:
                throw new BadRequestException('Authentication method not supported');
        }
        return [];
    }

    public function refreshAccessToken(string $refreshToken, string $authenticator): array
    {
        $authentication = $this->entityManager->getRepository('App:Authentication')->find($authenticator);
        if ($authentication instanceof Authentication === false) {
            return [];
        }
        $body = [
            'client_id'         => $authentication->getClientId(),
            'grant_type'        => 'refresh_token',
            'refresh_token'     => $refreshToken,
            'client_secret'     => $authentication->getSecret(),
        ];

        $response = $this->client->request('POST', $authentication->getTokenUrl(), [
            'form_params'  => $body,
            'content_type' => 'application/x-www-form-urlencoded',
        ]);

        $accessToken = json_decode($response->getBody()->getContents(), true);

        return $accessToken;
    }

    public function retrieveAdfsData(string $code, Authentication $authentication, string $redirectUrl): array
    {
        $body = [
            'client_id'         => $authentication->getClientId(),
            'client_secret'     => $authentication->getSecret(),
            'redirect_uri'      => $redirectUrl,
            'code'              => $code,
            'grant_type'        => 'authorization_code',
        ];

        $response = $this->client->request('POST', $authentication->getTokenUrl(), [
            'form_params'  => $body,
            'content_type' => 'application/x-www-form-urlencoded',
        ]);

        $accessToken = json_decode($response->getBody()->getContents(), true);

        return $accessToken;
    }

    public function handleAuthenticationUrl(string $method, string $identifier, string $redirectUrl): string
    {
        $authentication = $this->retrieveAuthentication($identifier);

        return $this->buildRedirectUrl($method, $redirectUrl, $authentication);
    }

    public function buildRedirectUrl(string $method, string $redirectUrl, Authentication $authentication): ?string
    {
        switch ($method) {
            case 'oidc':
                return $this->handleOidcRedirectUrl($redirectUrl, $authentication);
            case 'adfs':
                return $this->handleAdfsRedirectUrl($redirectUrl, $authentication);
            case 'digid':
                break;
            default:
                throw new BadRequestException('Authentication method not supported');
        }

        return null;
    }

    public function handleOidcRedirectUrl(string $redirectUrl, Authentication $authentication): string
    {
        $scopes = implode(' ', $authentication->getScopes());

        return "{$authentication->getAuthenticateUrl()}?client_id={$authentication->getClientId()}&response_type=code&scope={$scopes}&state={$this->session->getId()}&redirect_uri={$redirectUrl}";
    }

    public function handleAdfsRedirectUrl(string $redirectUrl, Authentication $authentication): string
    {
        return $authentication->getAuthenticateUrl().
            '?response_type=code&response_mode=query&client_id='.
            $authentication->getClientId().'&^redirect_uri='.
            $redirectUrl.
            '&scope='.
            implode(' ', $authentication->getScopes());
    }

    public function retrieveAuthentication(string $identifier): Authentication
    {
        $authentications = $this->entityManager->getRepository('App\Entity\Authentication')->findBy(['name' => $identifier]);

        if (count($authentications) == 0 || !$authentications[0] instanceof Authentication) {
            throw new NotFoundHttpException('Unable to find Authentication');
        }

        return $authentications[0];
    }
}
