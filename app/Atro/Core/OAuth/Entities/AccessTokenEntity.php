<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core\OAuth\Entities;

use DateTimeImmutable;
use Lcobucci\JWT\Token;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

/**
 * An access token, issued as a JWT. Besides the claims of the authorization server it carries the custom claims of
 * its OAuth client (OauthClientClaim).
 */
class AccessTokenEntity implements AccessTokenEntityInterface
{
    use AccessTokenTrait;
    use EntityTrait;
    use TokenEntityTrait;

    /**
     * The claims the authorization server sets itself; a custom claim cannot override them.
     */
    public const RESERVED_CLAIMS = ['iss', 'sub', 'aud', 'exp', 'nbf', 'iat', 'jti', 'scopes'];

    /**
     * @var array<string, string> claim name => value
     */
    private array $customClaims = [];

    /**
     * @param array<string, string> $customClaims claim name => value
     */
    public function setCustomClaims(array $customClaims): void
    {
        $this->customClaims = $customClaims;
    }

    /**
     * The JWT of league/oauth2-server (AccessTokenTrait::convertToJWT) with the custom claims added.
     */
    private function convertToJWT(): Token
    {
        $this->initJwtConfiguration();

        $builder = $this->jwtConfiguration->builder()
            ->permittedFor($this->getClient()->getIdentifier())
            ->identifiedBy($this->getIdentifier())
            ->issuedAt(new DateTimeImmutable())
            ->canOnlyBeUsedAfter(new DateTimeImmutable())
            ->expiresAt($this->getExpiryDateTime())
            ->relatedTo($this->getSubjectIdentifier())
            ->withClaim('scopes', $this->getScopes());

        foreach ($this->customClaims as $name => $value) {
            if (!in_array($name, self::RESERVED_CLAIMS, true)) {
                $builder = $builder->withClaim($name, $value);
            }
        }

        return $builder->getToken($this->jwtConfiguration->signer(), $this->jwtConfiguration->signingKey());
    }
}
