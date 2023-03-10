<?php

namespace App\Controller;

use Exception;
use Jose\Component\Checker\AlgorithmChecker;
use Jose\Component\Checker\AudienceChecker;
use Jose\Component\Checker\ClaimCheckerManager;
use Jose\Component\Checker\ExpirationTimeChecker;
use Jose\Component\Checker\HeaderCheckerManager;
use Jose\Component\Checker\IssuedAtChecker;
use Jose\Component\Checker\IssuerChecker;
use Jose\Component\Checker\NotBeforeChecker;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\HS512;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\JWSTokenSupport;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class JwtController extends AbstractController
{
    #[Route('/jwt', name: 'app_jwt', methods: ['GET'])]
    public function getToken(): JsonResponse
    {
        $algorithmManager = new AlgorithmManager([
            new HS512(),
        ]);

        $jwk = new JWK([
            'kty' => 'oct',
            'k' => $this->getParameter('app.jwt_secret'),
        ]);

        $jwsBuilder = new JWSBuilder($algorithmManager);

        $payload = json_encode([
            'roles' => 'user',
        ]);

        $jws = $jwsBuilder
            ->create()
            ->withPayload($payload)
            ->addSignature($jwk, [
                'alg' => 'HS512',
                'iat' => time(),
                'nbf' => time(),
                'exp' => time() + 3600,
                'iss' => 'symfony-jwt-0-issuer',
                'aud' => 'symfony-jwt-0-consumer',
            ])
            ->build();

        $serializer = new CompactSerializer();

        $token = $serializer->serialize($jws, 0);

        return $this->json([
            'token' => $token,
        ]);
    }

    #[Route('/jwt/{token}', methods: ['GET'])]
    public function decodeToken(string $token): JsonResponse
    {
        $algorithmManager = new AlgorithmManager([new HS512()]);
        $jwsVerifier = new JWSVerifier($algorithmManager);

        $headerCheckerManager = new HeaderCheckerManager(
            [
                new AlgorithmChecker(['HS512']),

                new IssuedAtChecker(protectedHeaderOnly: true),
                new NotBeforeChecker(protectedHeaderOnly: true),
                new ExpirationTimeChecker(protectedHeaderOnly: true),

                new IssuerChecker(['symfony-jwt-0-issuer'], protectedHeader: true),
                new AudienceChecker('symfony-jwt-0-consumer', protectedHeader: true),
            ],
            [
                new JWSTokenSupport(),
            ]
        );

        $jwk = new JWK([
            'kty' => 'oct',
            'k' => $this->getParameter('app.jwt_secret'),
        ]);

        $serializerManager = new JWSSerializerManager([new CompactSerializer()]);

        $jws = $serializerManager->unserialize($token);

        $isVerified = $jwsVerifier->verifyWithKey($jws, $jwk, 0);

        if (!$isVerified) {
            throw new Exception('The provided signature is invalid.');
        }

        $headerCheckerManager->check($jws, 0);

        $claimCheckerManager = new ClaimCheckerManager([]);

        $claims = json_decode($jws->getPayload(), true);

        $claimCheckerManager->check($claims, ['roles']);

        return $this->json([
            'token' => $token,
            'claims' => $claims,
        ]);
    }
}
