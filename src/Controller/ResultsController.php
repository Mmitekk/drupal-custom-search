<?php

namespace Drupal\custom_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
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

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
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
    $config = $this->config('custom_search.settings');
    $q = trim((string) $request->query->get('q', ''));
    $perPage = (int) $config->get('results_limit') ?: 10;
    $lang = $this->languageManager()->getCurrentLanguage()->getId();

    $build = [];
    $build['#attached']['library'][] = 'custom_search/search';
    // Direct stylesheet tag as well: it cannot be mangled by escaping and
    // does not depend on the library discovery cache.
    try {
      $module_path = \Drupal::service('extension.list.module')->getPath('custom_search');
      $base_path = rtrim(\Drupal::request()->getBasePath(), '/');
      $build['#attached']['html_head'][] = [
        [
          '#tag' => 'link',
          '#attributes' => [
            'rel' => 'stylesheet',
            'media' => 'all',
            'href' => $base_path . '/' . $module_path . '/css/custom-search.css?v=1.0.12',
          ],
        ],
        'custom_search_ext_css',
      ];
    }
    catch (\Exception) {
      // Discovery/request unavailable: library above stays.
    }
    $build['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => $this->styleVars($config),
      ],
      'custom_search_vars',
    ];
    $build['form'] = [
      '#theme' => 'custom_search_results_form',
      '#q' => $q,
      '#placeholder' => $config->get('placeholder') ?: 'Search…',
      '#button_size' => in_array($config->get('style_button_size'), ['s', 'm', 'l'], TRUE) ? $config->get('style_button_size') : 'm',
    ];

    if ($q === '') {
      $build['hint'] = [
        '#markup' => $this->t('Enter a query above to search the site.'),
      ];
      return $build;
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $allowed = $config->get('bundles') ?: [];
    $allowed = array_values(array_filter($allowed));

    $query = $storage->getQuery()
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->pager($perPage)
      ->sort('changed', 'DESC');

    if (!empty($allowed)) {
      $query->condition('type', $allowed, 'IN');
    }

    $or = $query->orConditionGroup()
      ->condition('title', $q, 'CONTAINS')
      ->condition('body.value', $q, 'CONTAINS');
    $query->condition($or);

    try {
      $ids = $query->execute();
    }
    catch (\Exception) {
      // Fallback to title-only search (e.g. when no bundle has a body field).
      \Drupal::logger('custom_search')->warning('Full-text results query failed, falling back to title search for "@q".', ['@q' => $q]);
      $fallback = $storage->getQuery()
        ->condition('status', 1)
        ->accessCheck(TRUE)
        ->pager($perPage)
        ->sort('changed', 'DESC');
      if (!empty($allowed)) {
        $fallback->condition('type', $allowed, 'IN');
      }
      $fallback->condition('title', $q, 'CONTAINS');
      try {
        $ids = $fallback->execute();
      }
      catch (\Exception) {
        $ids = [];
      }
    }
    $nodes = $ids ? $storage->loadMultiple($ids) : [];

    $items = [];
    foreach ($nodes as $node) {
      if (!$node instanceof NodeInterface || !$node->access('view')) {
        continue;
      }
      try {
        $url = $node->toUrl();
      }
      catch (\Exception) {
        continue;
      }
      $items[] = [
        'title' => $this->highlight($node->label(), $q),
        'url' => $url,
        'snippet' => $this->highlight($this->snippet($node, $q), $q),
        'type_label' => $config->get('show_type_label') ? $this->typeLabel($node->bundle()) : NULL,
      ];
    }

    if (empty($items)) {
      $template = ($lang === 'ru') ? $config->get('empty_text_ru') : $config->get('empty_text_en');
      $template = $template ?: 'Nothing found for "@q".';
      $build['empty'] = [
        '#markup' => $this->t($template, ['@q' => $q]),
      ];
    }
    else {
      $build['list'] = [
        '#theme' => 'custom_search_results_list',
        '#items' => $items,
      ];
      $build['pager'] = ['#type' => 'pager'];
    }

    $build['#cache'] = [
      'contexts' => ['url.query_args:q', 'user.permissions', 'languages'],
    ];

    return $build;
  }

  protected function snippet(NodeInterface $node, string $q): string {
    $text = '';
    if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
      $text = strip_tags((string) $node->get('body')->value);
      // Decode entities (&nbsp; etc.) so they render as text, not as code.
      $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $text = str_replace("\xC2\xA0", ' ', $text);
      $text = trim(preg_replace('/\s+/u', ' ', $text));
    }
    if ($text === '') {
      return $node->label();
    }
    // Cut around the first occurrence of the query when possible.
    $pos = mb_stripos($text, $q);
    if ($pos !== FALSE && mb_strlen($text) > 220) {
      $start = max(0, $pos - 80);
      $cut = mb_substr($text, $start, 220);
      return ($start > 0 ? '…' : '') . $cut . '…';
    }
    if (mb_strlen($text) > 220) {
      return mb_substr($text, 0, 220) . '…';
    }
    return $text;
  }

  protected function highlight(string $text, string $q): array {
    // Return a render array so <mark> is not escaped.
    $safe = ['#markup' => ''];
    $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $q = trim($q);
    if ($q === '') {
      $safe['#markup'] = $escaped;
      return $safe;
    }
    $quoted = preg_quote(htmlspecialchars($q, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), '/');
    $safe['#markup'] = preg_replace('/(' . $quoted . ')/iu', '<mark>$1</mark>', $escaped) ?? $escaped;
    $safe['#allowed_tags'] = ['mark'];
    return $safe;
  }

  protected function typeLabel(string $bundle): string {
    $map = [
      'service' => $this->t('Service'),
      'doctor' => $this->t('Doctor'),
      'page' => $this->t('Page'),
      'info' => $this->t('Info'),
    ];
    // Reuse the same heuristic as suggestions: keep labels human-friendly.
    $b = mb_strtolower($bundle);
    if (in_array($b, ['doctor', 'doctors', 'person', 'team', 'staff'], TRUE)) {
      return (string) $map['doctor'];
    }
    if (in_array($b, ['service', 'services', 'product'], TRUE)) {
      return (string) $map['service'];
    }
    if (in_array($b, ['article', 'news', 'blog', 'faq'], TRUE)) {
      return (string) $map['info'];
    }
    return (string) $map['page'];
  }

  /**
   * Builds a sanitized :root CSS variables string from style settings.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The custom_search.settings config object.
   */
  protected function styleVars($config): string {
    return \Drupal\custom_search\Plugin\Block\CustomSearchBlock::buildStyleVars($config);
  }

}
