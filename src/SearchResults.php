<?php

namespace Drupal\custom_search;

use Drupal\custom_search\Plugin\Block\CustomSearchBlock;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Builds the search results render array, shared by the route and the block.
 */
class SearchResults {

  /**
   * Builds the results render array for the current query string.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request (?q= is read from it).
   * @param string|null $action
   *   Form action override. NULL keeps the default /searching route;
   *   an empty string makes the form submit to the current page (block use).
   *
   * @return array
   *   Render array with the form, results list and pager.
   */
  public static function build(Request $request, $action = NULL): array {
    $config = \Drupal::config('custom_search.settings');
    $q = trim((string) $request->query->get('q', ''));
    $perPage = (int) $config->get('results_limit') ?: 10;
    $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();

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
            'href' => $base_path . '/' . $module_path . '/css/custom-search.css?v=1.1.0',
          ],
        ],
        'custom_search_ext_css',
      ];
    }
    catch (\Exception $e) {
      // Discovery/request unavailable: library above stays.
    }
    $build['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => self::styleVars($config),
      ],
      'custom_search_vars',
    ];
    // Pages showing actual results (any ?q=) must stay out of the search
    // engines' index — endless query variants are duplicate content. Pages
    // without a query (the empty form) stay indexable.
    if ($q !== '') {
      $build['#attached']['html_head'][] = [
        [
          '#tag' => 'meta',
          '#attributes' => [
            'name' => 'robots',
            'content' => 'noindex, follow',
          ],
        ],
        'custom_search_noindex',
      ];
    }
    $form = [
      '#theme' => 'custom_search_results_form',
      '#q' => $q,
      '#placeholder' => $config->get('placeholder') ?: 'Search…',
      '#button_size' => in_array($config->get('style_button_size'), ['s', 'm', 'l'], TRUE) ? $config->get('style_button_size') : 'm',
    ];
    if ($action !== NULL) {
      $form['#action'] = $action;
    }
    $build['form'] = $form;

    if ($q === '') {
      $build['hint'] = [
        '#markup' => t('Enter a query above to search the site.'),
      ];
      return $build;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('node');
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
    catch (\Exception $e) {
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
      catch (\Exception $e) {
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
      catch (\Exception $e) {
        continue;
      }
      $items[] = [
        'title' => self::highlight($node->label(), $q),
        'url' => $url,
        'snippet' => self::highlight(self::snippet($node, $q), $q),
        'type_label' => $config->get('show_type_label') ? self::typeLabel($node->bundle()) : NULL,
      ];
    }

    if (empty($items)) {
      $template = ($lang === 'ru') ? $config->get('empty_text_ru') : $config->get('empty_text_en');
      $template = $template ?: 'Nothing found for "@q".';
      $build['empty'] = [
        '#markup' => t($template, ['@q' => $q]),
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

  /**
   * Builds a plain-text snippet around the first query occurrence.
   */
  protected static function snippet(NodeInterface $node, string $q): string {
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

  /**
   * Highlights the query inside the text with <mark> (render-safe).
   *
   * @return array
   *   Render array so <mark> is not escaped.
   */
  protected static function highlight(string $text, string $q): array {
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

  /**
   * Maps a node bundle to a human-friendly type label.
   */
  protected static function typeLabel(string $bundle): string {
    $b = mb_strtolower($bundle);
    if (in_array($b, ['doctor', 'doctors', 'person', 'team', 'staff'], TRUE)) {
      return (string) t('Doctor');
    }
    if (in_array($b, ['service', 'services', 'product'], TRUE)) {
      return (string) t('Service');
    }
    if (in_array($b, ['article', 'news', 'blog', 'faq'], TRUE)) {
      return (string) t('Info');
    }
    return (string) t('Page');
  }

  /**
   * Builds a sanitized :root CSS variables string from style settings.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The custom_search.settings config object.
   */
  protected static function styleVars($config): string {
    return CustomSearchBlock::buildStyleVars($config);
  }

}
