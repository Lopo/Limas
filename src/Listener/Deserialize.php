<?php

namespace Limas\Listener;

use ApiPlatform\Serializer\SerializerContextBuilderInterface;
use ApiPlatform\Symfony\EventListener\DeserializeListener;
use ApiPlatform\Symfony\Util\RequestAttributesExtractor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;


final readonly class Deserialize
{
	public function __construct(
		private DenormalizerInterface             $denormalizer,
		private SerializerContextBuilderInterface $serializerContextBuilder,
		private DeserializeListener               $decorated
	)
	{
	}

	public function onKernelRequest(RequestEvent $event): void
	{
		$request = $event->getRequest();
		if ($request->isMethodCacheable() || $request->isMethod(Request::METHOD_DELETE)) {
			return;
		}
		if ('form' === $request->getContentTypeFormat()) {
			$this->denormalizeFormRequest($request);
		} else {
			$this->decorated->onKernelRequest($event);
		}
	}

	private function denormalizeFormRequest(Request $request): void
	{
		if (0 === count($attributes = RequestAttributesExtractor::extractAttributes($request))) {
			return;
		}

		$context = $this->serializerContextBuilder->createFromRequest($request, false, $attributes);
		$populated = $request->attributes->get('data');
		if (null !== $populated) {
			$context['object_to_populate'] = $populated;
		}

		$data = $request->request->all();
		$object = $this->denormalizer->denormalize($data, $attributes['resource_class'], null, $context);
		$request->attributes->set('data', $object);
	}
}
