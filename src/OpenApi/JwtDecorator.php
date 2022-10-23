<?php declare(strict_types=1);

namespace Limas\OpenApi;

use ApiPlatform\Core\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\Core\OpenApi\OpenApi;
use ApiPlatform\Core\OpenApi\Model;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


final class JwtDecorator
	implements OpenApiFactoryInterface
{
	public function __construct(
		private readonly OpenApiFactoryInterface $decorated,
		private readonly UrlGeneratorInterface   $urlGenerator
	)
	{
	}

	public function __invoke(array $context = []): OpenApi
	{
		$openApi = ($this->decorated)($context);
		$schemas = $openApi->getComponents()->getSchemas();

		$schemas['Token'] = new \ArrayObject([
			'type' => 'object',
			'properties' => [
				'token' => [
					'type' => 'string',
					'readOnly' => true,
				]
			]
		]);
		$schemas['Credentials'] = new \ArrayObject([
			'type' => 'object',
			'properties' => [
				'username' => [
					'type' => 'string',
					'example' => 'admin',
				],
				'password' => [
					'type' => 'string',
					'example' => 'admin',
				]
			]
		]);

		$pathItem = new Model\PathItem(
			ref: 'JWT Token',
			post: new Model\Operation(
				operationId: 'postCredentialsItem',
				tags: ['Token'],
				responses: [
					'200' => [
						'description' => 'Get JWT token',
						'content' => [
							'application/json' => [
								'schema' => [
									'$ref' => '#/components/schemas/Token',
								]
							]
						]
					]
				],
				summary: 'Get JWT token to login.',
				requestBody: new Model\RequestBody(
					description: 'Generate new JWT Token',
					content: new \ArrayObject([
						'application/json' => [
							'schema' => [
								'$ref' => '#/components/schemas/Credentials',
							]
						]
					])
				)
			)
		);
		$openApi->getPaths()->addPath($this->urlGenerator->generate('api_login_jwt'), $pathItem);

		return $openApi;
	}
}
