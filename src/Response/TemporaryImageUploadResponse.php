<?php

namespace Limas\Response;


class TemporaryImageUploadResponse
{
	public bool $success = true;
	public $response;


	public function __construct(public $image)
	{
		$this->response = $this->image;
	}
}
