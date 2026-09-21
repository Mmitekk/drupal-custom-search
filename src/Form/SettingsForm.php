<?php

namespace Drupal\custom_search\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form. Descriptions in Russian and English live in separate tabs.
 */
class SettingsForm extends ConfigFormBase {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'custom_search_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['custom_search.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('custom_search.settings');

    $form['tabs'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-main',
    ];

    // Main settings tab.
    $form['main'] = [
      '#type' => 'details',
      '#title' => $this->t('Main settings'),
      '#group' => 'tabs',
      '#open' => TRUE,
    ];
    $form['main']['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search field placeholder'),
      '#default_value' => $config->get('placeholder'),
      '#description' => $this->t('Example: "Search the site…". Shown inside the footer search input.'),
    ];
    $form['main']['min_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum query length for suggestions'),
      '#default_value' => $config->get('min_length') ?: 2,
      '#min' => 1,
      '#max' => 10,
    ];
    $form['main']['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Suggestions limit (dropdown)'),
      '#default_value' => $config->get('limit') ?: 8,
      '#min' => 1,
      '#max' => 20,
    ];
    $form['main']['results_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Results per page (full results page)'),
      '#default_value' => $config->get('results_limit') ?: 10,
      '#min' => 1,
      '#max' => 50,
    ];
    $form['main']['bundles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content types to search (empty = all)'),
      '#options' => $this->nodeTypeOptions(),
      '#default_value' => $config->get('bundles') ?: [],
    ];
    $form['main']['show_type_label'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show content-type label on the results page'),
      '#default_value' => (bool) $config->get('show_type_label'),
    ];

    // RU tab — описание на русском.
    $form['description_ru'] = [
      '#type' => 'details',
      '#title' => $this->t('Описание (RU)'),
      '#group' => 'tabs',
    ];
    $form['description_ru']['about_ru'] = [
      '#type' => 'item',
      '#markup' => $this->t('<p><strong>Custom Search</strong> — поиск для Drupal 10/11. Блок ставится в подвал сайта: при вводе запроса под полем появляются подсказки (название + описание + категория), стрелками ↑/↓ можно выбрать вариант, <strong>Enter</strong> открывает страницу выдачи <code>/searching?q=…</code>.</p><p>Горячие клавиши: <kbd>Ctrl/⌘ + K</kbd> — фокус на поиске, <kbd>Esc</kbd> — закрыть подсказки.</p>'),
    ];
    $form['description_ru']['header_ru'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Заголовок страницы выдачи (RU)'),
      '#default_value' => $config->get('header_ru'),
    ];
    $form['description_ru']['empty_text_ru'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Текст «ничего не найдено» (RU). Используйте @q для запроса.'),
      '#default_value' => $config->get('empty_text_ru'),
      '#rows' => 2,
    ];

    // EN tab — description in English.
    $form['description_en'] = [
      '#type' => 'details',
      '#title' => $this->t('Description (EN)'),
      '#group' => 'tabs',
    ];
    $form['description_en']['about_en'] = [
      '#type' => 'item',
      '#markup' => $this->t('<p><strong>Custom Search</strong> is a footer live search for Drupal 10/11. Place the block in the footer: as you type, a dropdown suggests pages (title + snippet + category), ↑/↓ picks a suggestion, <strong>Enter</strong> opens the results page <code>/searching?q=…</code>.</p><p>Shortcuts: <kbd>Ctrl/⌘ + K</kbd> focuses search, <kbd>Esc</kbd> closes suggestions.</p>'),
    ];
    $form['description_en']['header_en'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Results page header (EN)'),
      '#default_value' => $config->get('header_en'),
    ];
    $form['description_en']['empty_text_en'] = [
      '#type' => 'textarea',
      '#title' => $this->t('“Nothing found” text (EN). Use @q for the query.'),
      '#default_value' => $config->get('empty_text_en'),
      '#rows' => 2,
    ];

    // Style tab — colors for the dropdown and the results page.
    $form['style'] = [
      '#type' => 'details',
      '#title' => $this->t('Оформление (Style)'),
      '#group' => 'tabs',
      '#description' => $this->t('Colors of suggestion titles, links, badges, focus ring, buttons and match highlighting.'),
    ];
    $form['style']['style_accent'] = [
      '#type' => 'color',
      '#title' => $this->t('Accent color (titles, links, badges, buttons)'),
      '#default_value' => $config->get('style_accent') ?: '#ea184f',
    ];
    $form['style']['style_dropdown_bg'] = [
      '#type' => 'color',
      '#title' => $this->t('Dropdown background'),
      '#default_value' => $config->get('style_dropdown_bg') ?: '#ffffff',
    ];
    $form['style']['style_text'] = [
      '#type' => 'color',
      '#title' => $this->t('Descriptions/snippets text color'),
      '#default_value' => $config->get('style_text') ?: '#4c6767',
    ];
    $form['style']['style_highlight'] = [
      '#type' => 'color',
      '#title' => $this->t('Match highlight background (<mark>)'),
      '#default_value' => $config->get('style_highlight') ?: '#fde3ea',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('custom_search.settings')
      ->set('placeholder', $form_state->getValue('placeholder'))
      ->set('min_length', (int) $form_state->getValue('min_length'))
      ->set('limit', (int) $form_state->getValue('limit'))
      ->set('results_limit', (int) $form_state->getValue('results_limit'))
      ->set('bundles', $form_state->getValue('bundles'))
      ->set('show_type_label', (bool) $form_state->getValue('show_type_label'))
      ->set('header_ru', $form_state->getValue('header_ru'))
      ->set('empty_text_ru', $form_state->getValue('empty_text_ru'))
      ->set('header_en', $form_state->getValue('header_en'))
      ->set('empty_text_en', $form_state->getValue('empty_text_en'))
      ->set('style_accent', $form_state->getValue('style_accent'))
      ->set('style_dropdown_bg', $form_state->getValue('style_dropdown_bg'))
      ->set('style_text', $form_state->getValue('style_text'))
      ->set('style_highlight', $form_state->getValue('style_highlight'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  protected function nodeTypeOptions(): array {
    $options = [];
    try {
      $types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
      foreach ($types as $type) {
        $options[$type->id()] = $type->label();
      }
    }
    catch (\Exception) {
    }
    return $options;
  }

}
