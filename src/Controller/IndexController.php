<?php

namespace Limas\Controller;

use Composer\InstalledVersions;
use Imagine\Image\AbstractImagine;
use Imagine\Image\Format;
use Limas\Service\GridPresetService;
use Limas\Service\SystemService;
use Nette\Utils\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;


class IndexController
	extends AbstractController
{
	public function __construct(
		private readonly SystemService     $systemService,
		private readonly array             $limas,
		private readonly RouterInterface   $router,
		private readonly GridPresetService $gridPresetService,
		private readonly KernelInterface   $kernel,
		private readonly AbstractImagine   $liipImagine
	)
	{
	}

	#[Route('/', name: 'index')]
	public function index(): Response
	{
		if ($this->limas['maintenance']) {
			return $this->render('index/maintenance.html.twig', [
				'maintenanceTitle' => $this->limas['maintenance']['title'],
				'maintenanceMessage' => $this->limas['maintenance']['message']
			]);
		}
		return $this->render($this->kernel->isDebug() ? 'index/index-dev.html.twig' : 'index/index.html.twig', $this->getRenderParameters());
	}

	private function getRenderParameters(): array
	{
		$aParameters = [
			'doctrine_orm_version' => InstalledVersions::getVersion('doctrine/orm'),
			'doctrine_dbal_version' => InstalledVersions::getVersion('doctrine/dbal'),
			'doctrine_common_version' => InstalledVersions::getVersion('doctrine/common'),
			'php_version' => PHP_VERSION,
			'auto_start_session' => true,
			'maxUploadSize' => false !== ($val = $this->getLimasParameterWithDefault('upload.limit', false))
				? $val
				: min($this->systemService->getBytesFromHumanReadable(ini_get('post_max_size')), $this->systemService->getBytesFromHumanReadable(ini_get('upload_max_filesize'))),
			'isOctoPartAvailable' => ($this->getLimasParameterWithDefault('octopart.nexarId', '') !== '' && $this->getLimasParameterWithDefault('octopart.nexarSecret', '') !== ''),
			'availableImageFormats' => array_map(static fn(Format $format): string => $format->getMimeType(), $this->liipImagine->getDriverInfo()->getSupportedFormats()->getAll()),
			'max_users' => $this->getLimasParameterWithDefault('auth.max_users', 'unlimited'),
			'authentication_provider' => $this->limas['authentication_provider'],
			'tip_of_the_day_uri' => $this->limas['tip_of_the_day_uri'],
			'password_change' => $this->getLimasParameterWithDefault('auth.allow_password_change', true),
			'defaultGridPresets' => Json::encode($this->gridPresetService->getDefaultPresets())
		];
		if ($this->getLimasParameterWithDefault('frontend.auto_login.enabled', false) === true) {
			$aParameters['autoLoginUsername'] = $this->limas['frontend']['auto_login']['username'];
			$aParameters['autoLoginPassword'] = $this->limas['frontend']['auto_login']['password'];
		}
		if ('' !== ($val = $this->getLimasParameterWithDefault('frontend.motd', ''))) {
			$aParameters['motd'] = $val;
		}

		return [
			'parameters' => $aParameters,
			'debug' => $this->kernel->isDebug(),
			'baseUrl' => $this->getBaseUrl()
		];
	}

	private function getBaseUrl(): string
	{
		$baseUrl = $this->getLimasParameterWithDefault('frontend.base_url', false);
		if ($baseUrl !== false) {
			return $baseUrl;
		}
		return $this->router->getContext()->getBaseUrl();
	}

	private function getLimasParameterWithDefault(string $name, mixed $default): mixed
	{
		$lpart = $this->limas;
		foreach (explode('.', $name) as $part) {
			if (!isset($lpart[$part])) {
				return $default;
			}
			$lpart = $lpart[$part];
		}
		return $lpart;
	}
}
