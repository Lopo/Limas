<?php

namespace Limas\Listener;

use ApiPlatform\Serializer\SerializerContextBuilderInterface;
use ApiPlatform\Symfony\EventListener\DeserializeListener;
use ApiPlatform\Util\RequestAttributesExtractor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;


final class Deserialize
{
	public function __construct(
		private readonly DenormalizerInterface             $denormalizer,
		private readonly SerializerContextBuilderInterface $serializerContextBuilder,
		private readonly DeserializeListener               $decorated
	)
	{
	}

	public function onKernelRequest(RequestEvent $event): void
	{
		$request = $event->getRequest();
		if ($request->isMethodCacheable(false) || $request->isMethod(Request::METHOD_DELETE)) {
			return;
		}
		if ('form' === $request->getContentType()) {
			$this->denormalizeFormRequest($request);
		} else {
			$this->decorated->onKernelRequest($event);
		}
	}

	private function denormalizeFormRequest(Request $request): void
	{
		if (!$attributes = RequestAttributesExtractor::extractAttributes($request)) {
			return;
		}

		$context = $this->serializerContextBuilder->createFromRequest($request, false, $attributes);
		if (null !== ($populated = $request->attributes->get('data'))) {
			$context['object_to_populate'] = $populated;
		}

		$data = $request->request->all();
		$object = $this->denormalizer->denormalize($data, $attributes['resource_class'], null, $context);
		$request->attributes->set('data', $object);
	}
}
