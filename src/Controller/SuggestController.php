<?php

namespace Drupal\custom_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns JSON suggestions for the live search dropdown.
 *
 * Mimics inpramed.ru: GET /custom-search/suggest?q=... returns:
 * { "results": [{ "type": "service|doctor|page|info", "title": "...", "description": "...", "path": "/node/1" }] }
 */
class SuggestController extends ControllerBase {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  public function suggest(Request $request): JsonResponse {
    $config = $this->config('custom_search.settings');
    $q = trim((string) $request->query->get('q', ''));
    $minLength = (int) $config->get('min_length') ?: 2;
    $limit = (int) $config->get('limit') ?: 8;

    if (mb_strlen($q) < $minLength) {
      return new JsonResponse(['results' => []]);
    }

    $nodes = $this->searchNodes($q, $limit);
    $results = [];
    foreach ($nodes as $node) {
      if (!$node instanceof NodeInterface || !$node->access('view')) {
        continue;
      }
      try {
        $url = $node->toUrl()->toString();
      }
      catch (\Exception) {
        continue;
      }
      $results[] = [
        'type' => $this->mapType($node->bundle()),
        'title' => $node->label(),
        'description' => $this->buildDescription($node),
        'path' => $url,
      ];
    }

    // Allow caching per query string, vary by permissions.
    $response = new JsonResponse(['results' => $results]);
    $response->headers->set('X-Drupal-Cache-Contexts', 'user.permissions');
    return $response;
  }

  /**
   * Searches published nodes by title (and body when available).
   *
   * @return \Drupal\node\NodeInterface[]
   */
  protected function searchNodes(string $q, int $limit): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $allowed = $this->config('custom_search.settings')->get('bundles') ?: [];
    // Keep only non-empty values (checkboxes return 0 for unchecked).
    $allowed = array_values(array_filter($allowed));

    $query = $storage->getQuery()
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->range(0, $limit)
      ->sort('changed', 'DESC');

    if (!empty($allowed)) {
      $query->condition('type', $allowed, 'IN');
    }

    $or = $query->orConditionGroup()
      ->condition('title', $q, 'CONTAINS')
      ->condition('body.value', $q, 'CONTAINS');
    $query->condition($or);

    $ids = $query->execute();
    if (empty($ids)) {
      return [];
    }
    return $storage->loadMultiple($ids);
  }

  /**
   * Maps a node bundle to one of: service, doctor, page, info.
   */
  protected function mapType(string $bundle): string {
    $bundle = mb_strtolower($bundle);
    $doctors = ['doctor', 'doctors', 'person', 'personnel', 'team', 'staff', 'vrachi', 'vrach'];
    $services = ['service', 'services', 'product', 'products', 'usluga', 'uslugi'];
    $info = ['article', 'news', 'blog', 'faq', 'stories', 'stati', 'novosti'];

    if (in_array($bundle, $doctors, TRUE)) {
      return 'doctor';
    }
    if (in_array($bundle, $services, TRUE)) {
      return 'service';
    }
    if (in_array($bundle, $info, TRUE)) {
      return 'info';
    }
    return 'page';
  }

  protected function buildDescription(NodeInterface $node): string {
    if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
      $text = strip_tags((string) $node->get('body')->value);
      $text = trim(preg_replace('/\s+/u', ' ', $text));
      if (mb_strlen($text) > 140) {
        $text = mb_substr($text, 0, 140) . '…';
      }
      return $text;
    }
    $type = $node->type->entity ? $node->type->entity->label() : $node->bundle();
    return (string) $type;
  }

}
