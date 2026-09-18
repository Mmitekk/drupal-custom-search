<?php

namespace Drupal\custom_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "Custom Search" block (inpramed-style footer search).
 *
 * Place it in the footer: input with live suggestions, Enter opens
 * the full results page.
 *
 * @Block(
 *   id = "custom_search_block",
 *   admin_label = @Translation("Custom Search (inpramed-style)"),
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
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['placeholder_override'] = $form_state->getValue('placeholder_override');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getModuleConfig();
    $placeholder = $this->configuration['placeholder_override'] ?: ($config->get('placeholder') ?: $this->t('Search the site…'));
    $minLength = (int) $config->get('min_length') ?: 2;

    return [
      '#theme' => 'custom_search_block',
      '#placeholder' => $placeholder,
      '#suggest_url' => Url::fromRoute('custom_search.suggest')->toString(),
      '#results_url' => Url::fromRoute('custom_search.results')->toString(),
      '#min_length' => $minLength,
      '#attached' => [
        'library' => ['custom_search/search'],
        'drupalSettings' => [
          'customSearch' => [
            'suggestUrl' => Url::fromRoute('custom_search.suggest')->toString(),
            'resultsUrl' => Url::fromRoute('custom_search.results')->toString(),
            'minLength' => $minLength,
          ],
        ],
      ],
      '#cache' => [
        'contexts' => ['user.permissions', 'languages'],
      ],
    ];
  }

  protected function getModuleConfig() {
    return \Drupal::config('custom_search.settings');
  }

}
