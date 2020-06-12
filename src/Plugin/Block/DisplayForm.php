<?php

namespace Drupal\heritage_ui\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block to Display Sources of a Heritage Text, .
 *
 * @Block(
 *   id = "heritage_chapter_menu",
 *   admin_label = @Translation("Chapter Select of Heritage Text in Menu"),
 *   category = @Translation("Custom")
 * )
 */
class DisplayForm extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $builtForm = \Drupal::formBuilder()->getForm('Drupal\heritage_ui\Form\DisplayCheckBoxes');
    $build = [];

    $build['form'] = $builtForm;
    // $build['#markup'] = render($render);
    $build['#cache']['max-age'] = 0;
    return $build;

  }

}
