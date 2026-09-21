<?php

namespace Drupal\custom_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "Custom Search" block (footer live search).
 *
 * Place it in the footer: input with live suggestions, Enter opens
 * the full results page.
 *
 * @Block(
 *   id = "custom_search_block",
 *   admin_label = @Translation("Custom Search"),
 *   category = @Translation("Search")
 * )
 */
class CustomSearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'placeholder_override' => '',
      'sticky' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['placeholder_override'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder override (leave empty to use global)'),
      '#default_value' => $this->configuration['placeholder_override'] ?? '',
    ];
    $form['sticky'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Pin the search bar to the bottom of the screen (sticky)'),
      '#description' => $this->t('Shows a fixed search bar at the bottom of every page the block is visible on.'),
      '#default_value' => !empty($this->configuration['sticky']),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['placeholder_override'] = $form_state->getValue('placeholder_override');
    $this->configuration['sticky'] = (bool) $form_state->getValue('sticky');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getModuleConfig();
    $placeholder = $this->configuration['placeholder_override'] ?: ($config->get('placeholder') ?: $this->t('Search the site…'));
    $minLength = (int) $config->get('min_length') ?: 2;

    // Resolve routable URLs, but never break the page if the router is
    // temporarily stale (e.g. right after enabling the module): fall back
    // to plain paths in that case.
    try {
      $suggest_url = Url::fromRoute('custom_search.suggest')->toString();
      $results_url = Url::fromRoute('custom_search.results')->toString();
    }
    catch (\Exception) {
      $suggest_url = '/searching/suggest';
      $results_url = '/searching';
    }

    $build = [
      '#theme' => 'custom_search_block',
      '#placeholder' => $placeholder,
      '#suggest_url' => $suggest_url,
      '#results_url' => $results_url,
      '#min_length' => $minLength,
      // Existing placements predate the sticky option: default them to ON.
      '#sticky' => $this->configuration['sticky'] ?? TRUE,
      '#attached' => [
        'library' => ['custom_search/search'],
        'drupalSettings' => [
          'customSearch' => [
            'suggestUrl' => $suggest_url,
            'resultsUrl' => $results_url,
            'minLength' => $minLength,
          ],
        ],
      ],
      '#cache' => [
        'contexts' => ['user.permissions', 'languages'],
      ],
    ];

    // Direct file tags as well, so the block looks and works even when the
    // library discovery cache is stale. Plain link/script tags cannot be
    // mangled by HTML-escaping (unlike inlined code), and once() guards the
    // double init when the library above also loads on healthy sites.
    // Dynamic colors from settings ride along in a tiny style tag.
    try {
      $module_path = \Drupal::service('extension.list.module')->getPath('custom_search');
      $base_path = rtrim(\Drupal::request()->getBasePath(), '/');
      $css_url = $base_path . '/' . $module_path . '/css/custom-search.css?v=1.0.11';
      $js_url = $base_path . '/' . $module_path . '/js/custom-search.js?v=1.0.11';
      $build['#attached']['html_head'][] = [
        [
          '#tag' => 'link',
          '#attributes' => [
            'rel' => 'stylesheet',
            'media' => 'all',
            'href' => $css_url,
          ],
        ],
        'custom_search_ext_css',
      ];
      $build['#attached']['html_head'][] = [
        [
          '#tag' => 'script',
          '#attributes' => [
            'src' => $js_url,
            'defer' => TRUE,
          ],
        ],
        'custom_search_ext_js',
      ];
      $build['#attached']['html_head'][] = [
        [
          '#tag' => 'style',
          '#value' => $this->styleVars(),
        ],
        'custom_search_vars',
      ];
    }
    catch (\Exception) {
      // Discovery/request unavailable (e.g. CLI render): library above stays.
    }

    return $build;
  }

  protected function getModuleConfig() {
    return \Drupal::config('custom_search.settings');
  }

  /**
   * Builds a sanitized :root CSS variables string from style settings.
   */
  protected function styleVars(): string {
    $config = $this->getModuleConfig();
    $defaults = [
      '--cs-accent' => '#ea184f',
      '--cs-dropdown-bg' => '#ffffff',
      '--cs-text' => '#4c6767',
      '--cs-highlight' => '#fde3ea',
    ];
    $map = [
      'style_accent' => '--cs-accent',
      'style_dropdown_bg' => '--cs-dropdown-bg',
      'style_text' => '--cs-text',
      'style_highlight' => '--cs-highlight',
    ];
    foreach ($map as $key => $var) {
      $value = $config->get($key);
      if (is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
        $defaults[$var] = $value;
      }
    }
    $out = ':root{';
    foreach ($defaults as $var => $value) {
      $out .= $var . ':' . $value . ';';
    }
    return $out . '}';
  }

}
