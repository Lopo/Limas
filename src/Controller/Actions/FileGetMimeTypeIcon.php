<?php

namespace Limas\Controller\Actions;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class FileGetMimeTypeIcon
	extends FileActions
{
	public function __invoke(Request $request, int $id): Response
	{
		return new BinaryFileResponse(
			$this->mimetypeIconService->getMimetypeIcon($this->entityManager->find($this->getEntityClass($request), $id)->getMimetype()),
			Response::HTTP_OK,
			[],
			false,
			null,
			true,
			true
		);
	}
}
