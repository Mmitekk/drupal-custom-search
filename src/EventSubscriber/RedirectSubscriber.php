<?php

namespace Drupal\custom_search\EventSubscriber;

use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects core search pages to Custom Search.
 *
 * /search/node?keys=... (core Search, may be linked by old blocks or indexed
 * by search engines) → 301 to /searching?q=... so visitors always land on
 * the Custom Search results page.
 */
class RedirectSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run before the router resolves the request.
    return [KernelEvents::REQUEST => ['onRequest', 100]];
  }

  /**
   * Redirects /search/node to the Custom Search results page.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    if ($request->getPathInfo() !== '/search/node' || !$request->isMethodCacheable()) {
      return;
    }
    $keys = trim((string) $request->query->get('keys', ''));
    $options = $keys !== '' ? ['query' => ['q' => $keys]] : [];
    $url = Url::fromRoute('custom_search.results', [], $options)->setAbsolute()->toString();
    $event->setResponse(new RedirectResponse($url, Response::HTTP_MOVED_PERMANENTLY));
  }

}
