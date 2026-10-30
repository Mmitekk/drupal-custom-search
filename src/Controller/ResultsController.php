<?php

namespace Drupal\custom_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\custom_search\SearchResults;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Full search results page. Opened when the user presses Enter.
 *
 * Path: /searching?q=...
 */
class ResultsController extends ControllerBase {

  /**
   * Entity type manager (inherited untyped property from ControllerBase).
   *
   * NOTE: do not redeclare $entityTypeManager with a type here — the parent
   * class already defines it untyped and PHP fatals on narrowing.
   */

  public function __construct(\Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  public function title(Request $request): string {
    $q = trim((string) $request->query->get('q', ''));
    $lang = $this->languageManager()->getCurrentLanguage()->getId();
    $config = $this->config('custom_search.settings');
    $header = ($lang === 'ru') ? $config->get('header_ru') : $config->get('header_en');
    $header = $header ?: 'Search results';
    return $q !== '' ? $header . ' — «' . $q . '»' : (string) $header;
  }

  public function results(Request $request): array {
    return SearchResults::build($request);
  }

}
