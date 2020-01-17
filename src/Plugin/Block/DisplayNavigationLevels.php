<?php

namespace Drupal\heritage_ui\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block to Display Sources of a Heritage Text, .
 *
 * @Block(
 *   id = "display_navigation_levels",
 *   admin_label = @Translation("Select the navigation levels of a text"),
 *   category = @Translation("Custom")
 * )
 */
class DisplayNavigationLevels extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $builtForm = \Drupal::formBuilder()->getForm('Drupal\heritage_ui\Form\NavigationLevels');
    $build = [];
    $build['form'] = $builtForm;
    $build['#cache']['max-age'] = 0;
    return $build;

  }

}
