<?php

namespace Drupal\custom_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\custom_search\SearchResults;

/**
 * Provides a "Custom Search results" block.
 *
 * Place it into any page (e.g. a "Search" page node) to render the search
 * form and results there. Themes that keep the site chrome in node
 * templates (empty header/footer regions) get a fully themed search page
 * this way — the block lives on a regular node page.
 *
 * @Block(
 *   id = "custom_search_results_block",
 *   admin_label = @Translation("Custom Search results"),
 *   category = @Translation("Search")
 * )
 */
class CustomSearchResultsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    // The form submits back to the current page, so the block works on any
    // path it is placed on.
    return SearchResults::build(\Drupal::request(), '');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['url.query_args:q', 'user.permissions', 'languages'];
  }

}
